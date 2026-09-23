<?php

namespace App\Http\Controllers\sts;

use App\Http\Controllers\Controller;
use App\Http\Controllers\consultas\ConsultasController;
use App\Http\Controllers\varios\ConsultaIdentidadExternoController;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Direccion;
use App\Models\openceo\Telefono;
use App\Models\sts\ClientesMultinivel;
use App\Models\sts\ClientesMultinivelConsulta;
use App\Servicios\Consultas\ConsultasService;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DynamoClienteController extends Controller
{
    // Un cliente a CRÉDITO conserva su política al editarse desde aquí (nunca se degrada a
    // contado), pero eso activa las validaciones de crédito de crm.fn_cliente_validaciones.
    // Si le faltan datos que este formulario no captura, no hay nada que el corredor pueda
    // hacer: se le dice dónde se resuelve.
    private const MSG_CLIENTE_INCOMPLETO_CREDITO = 'Este cliente tiene datos pendientes y se administra desde el CRM. Comuníquese con Almacenes España.';

    // Mensaje amigable para cada excepción que pueden lanzar crm.fn_clientes_registrar y
    // crm.fn_clientes_modificar (ambas validan por crm.fn_cliente_validaciones, así que
    // comparten el catálogo de códigos). Mismo mapa del ClienteController de openceo, salvo
    // los códigos de CRÉDITO: aquí el corredor no tiene cómo completar esos datos desde el
    // formulario, así que se le indica que el cliente se administra en el CRM.
    private const MENSAJES_ERROR = [
        'REQUIERE_TIPOS_PAGO' => 'Debe registrar al menos un tipo de pago.',
        'REQUIERE_AGENTE' => 'Debe seleccionar un agente.',
        'REQUIRE_IDENTIFICACION' => 'Debe ingresar la identificación.',
        'REQUIERE_UBICACION' => 'Debe seleccionar una ubicación.',
        'REQUIERE_CATEGORIA' => 'Debe seleccionar una categoría.',
        'REQUIERE_ZONA' => 'Debe seleccionar una zona.',
        'REQUIERE_CANAL' => 'Debe seleccionar un canal.',
        'REQUIERE_LISTAPRE' => 'Debe seleccionar una lista de precios.',
        'REQUIERE_PAIS' => 'Debe seleccionar el país / nacionalidad.',
        'REQUIERE_SEXO' => 'Debe seleccionar el sexo.',
        'REQUIERE_ESTADOCIVIL' => 'Debe seleccionar el estado civil.',
        'REQUIERE_NIVELESTUDIOS' => 'Debe seleccionar el nivel de estudios.',
        'REQUIERE_VIVIENDA' => 'Debe seleccionar el tipo de vivienda.',
        'REQUIERE_SITLABORAL' => 'Debe seleccionar la situación laboral.',
        'REQUIERE_TIPOEMPRESA' => 'Debe seleccionar el tipo de empresa.',
        'REQUIERE_INGRESOSPERSONALES' => 'Debe seleccionar la fuente de ingresos personales.',
        'REQUIERE_CARGASFAMILIARES' => 'Debe ingresar el número de cargas familiares.',
        'REQUIERE_INGRESOSACTIVIDAD' => 'Debe ingresar los ingresos mensuales.',
        'REQUIERE_EGRESOSACTIVIDAD' => 'Debe ingresar los egresos mensuales.',
        'IDENTIFICACION_INVALIDA' => 'La identificación ingresada no es válida.',
        'IDENTIFICACION_DUPLICADA' => 'Ya existe un cliente con esa identificación.',
        'CODIGO_DUPLICADO' => 'Ya existe un cliente con ese código.',
        'CLIENTE_NO_ENCONTRADO' => 'No se encontró el cliente a actualizar.',
        'POLITICA_INVALIDA' => 'La política seleccionada no es válida.',
        // CRÉDITO: el cliente existe pero le faltan datos que solo se cargan en el CRM.
        'EMAILINCO' => self::MSG_CLIENTE_INCOMPLETO_CREDITO,
        'CANTON' => self::MSG_CLIENTE_INCOMPLETO_CREDITO,
        'AECONOMICA' => self::MSG_CLIENTE_INCOMPLETO_CREDITO,
        'EMPRESA' => self::MSG_CLIENTE_INCOMPLETO_CREDITO,
        'PARROQUIA' => self::MSG_CLIENTE_INCOMPLETO_CREDITO,
        'REFERENCIAS' => self::MSG_CLIENTE_INCOMPLETO_CREDITO,
    ];

    // Constantes del canal STS. Solo se aplican al ALTA: en una edición estos valores salen
    // de la ficha del propio cliente, nunca de aquí (si no, se le cambiaría el agente y la
    // política a un cliente que ya existe).
    private const TTE_ID_CELULAR = 2;
    private const DIR_TIPO_DEFAULT = 'CASA';
    private const SFP_ID_DEFAULT = 1;
    private const EMP_ID_DEFAULT = 1;
    private const CLI_SEXO_DEFAULT = 'M';
    private const CLI_ESTADOCIVIL_DEFAULT = 'S';

    // Usuario del proveedor que generó el enlace, leído del token cifrado. Las rutas del
    // formulario no llevan JWT, así que esta es la única identidad disponible en ese canal:
    // alimenta el log de consultas de identidad (crm.consulta_identidad.usu_id) y el usuario_id
    // de la auditoría forense. Queda en null con enlaces emitidos antes de incluirlo en el token.
    private ?int $usuIdEnlace = null;

    // Version 1.0
    // Busca sobre ENTIDAD (no solo sobre cliente) y distingue tres estados: no existe / la
    // persona existe pero aún no es cliente / ya es cliente. Devuelve únicamente los campos
    // que muestra el formulario.
    public function verificarClienteDynamo(Request $request)
    {
        try {
            $tipoidentificacion = trim($request->input('tipoidentificacion'));
            $identificacion = trim($request->input('identificacion'));

            if (empty($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
            }
            if (empty($tipoidentificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar el tipo de identificación.', null));
            }

            // Extraer el corredor del token cifrado (igual que addDynamoCliente)
            $tokenData = $this->validarTokenEnlace($request);
            if ($tokenData instanceof JsonResponse) {
                return $tokenData;
            }
            $corredor = trim($tokenData['corredor']);

            // Días de vigencia de la vinculación cliente-corredor (parámetro CLICOR)
            $diasCorredor = $this->obtenerDiasParametroCorredor();
            if ($diasCorredor instanceof JsonResponse) {
                return $diasCorredor;
            }

            if ($tipoidentificacion == 1) {
                if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
                }
            } elseif ($tipoidentificacion == 2) {
                if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
                }
            } else {
                // Solo cédula y RUC: el pasaporte no tiene fuente oficial de la que sacar el nombre.
                return response()->json(RespuestaApi::returnResultado('error', 'Solo se admite cédula o RUC.', null));
            }

            // La foto sale de la MISMA función que usa el CRUD del CRM
            // (crm.fn_cliente_buscar_por_identificacion). Busca sobre public.entidad con
            // LEFT JOIN cliente ... AND cli_tipocli = 1, así que a diferencia del SELECT
            // anterior —que miraba cliente.cli_codigo— también encuentra a una persona que
            // YA existe en el sistema pero todavía no es cliente (p.ej. un proveedor).
            $foto = $this->fotoCliente($identificacion, (int) $tipoidentificacion);

            // YA ES CLIENTE (cli_tipocli = 1): el formulario no lo toca —ni lo casa ni lo
            // modifica—, esté libre o vinculado a cualquier corredor, y NO se consulta a
            // GaranCheck. La consulta queda registrada igual, con existia = true.
            if ($foto && !empty($foto['cli_id'])) {
                $this->registrarConsultaCorredor($corredor, $identificacion, (int) $foto['cli_id'], true);

                return response()->json(RespuestaApi::returnResultado('error', 'El cliente ya existe', null));
            }

            // Estados A (no existe) y B (existe como proveedor/garante, sin cliente).
            // ESTADO A/B anteriores, desactivados: el nombre ya no sale de Ecuador Legal/SRI
            // ni de la ficha, sino del JSON de GaranCheck.
            // if (!$foto) {
            //     return response()->json(RespuestaApi::returnResultado('success', 'El cliente no existe', null));
            // }
            // if (empty($foto['cli_id'])) {
            //     return response()->json(RespuestaApi::returnResultado('success', 'La persona ya está registrada. Verifique los datos para registrarla como cliente.', $this->datosFormulario($foto)));
            // }

            // NO es cliente (no existe la entidad, o existe como proveedor/garante): se registra la
            // consulta y se va a GaranCheck, que crea al cliente con los datos del proveedor y
            // guarda historial + resumen. El corredor solo completará email y celular al Guardar.
            // Si GaranCheck no atiende, procesarConsulta cae a Ecuador Legal / SRI ($conRespaldo).
            $consulta = $this->registrarConsultaCorredor($corredor, $identificacion, null, false, ConsultasService::PROVEEDOR_GARANCHECK);

            $respuesta = app(ConsultasController::class)->procesarConsulta(
                $request,
                $identificacion,
                $corredor,
                isset($tokenData['usu_id']) ? (int) $tokenData['usu_id'] : null,
                true
            );

            $datos = $respuesta->getData(true);

            if (($datos['status'] ?? '') !== 'success') {
                return $respuesta;
            }

            $cliId = $datos['data']['cli_id'] ?? null;
            $proveedorUsado = $datos['data']['proveedor'] ?? ConsultasService::PROVEEDOR_GARANCHECK;

            if ($proveedorUsado === ConsultasService::PROVEEDOR_GARANCHECK) {
                // Respondió el buró: se completa la fila que ya se había abierto.
                if ($cliId) {
                    $consulta->cli_id = (int) $cliId;
                    $consulta->save();
                }
            } else {
                // Hubo plan B. La fila de GaranCheck queda con cli_id NULL —es la evidencia de que
                // se cayó— y el alta se registra en una fila nueva con la fuente que sí respondió.
                $this->registrarConsultaCorredor($corredor, $identificacion, $cliId ? (int) $cliId : null, false, $proveedorUsado);
            }

            // Solo lo que el formulario muestra. El cli_id y el detalle del buró no salen a un
            // endpoint público que se abre con un enlace.
            return response()->json(RespuestaApi::returnResultado('success', 'Consulta realizada con éxito', [
                'nombres' => $datos['data']['nombres'] ?? null,
                'apellidos' => $datos['data']['apellidos'] ?? null,
                'tipo_sujeto' => $datos['data']['tipo_sujeto'] ?? 'N',
                'proveedor' => $proveedorUsado,
                'evaluacion' => $datos['data']['evaluacion'] ?? null,
            ]));

            // Lógica anterior (libre / mismo corredor / otro corredor), desactivada:
            // $vinculado = $this->corredorVinculado((int) $foto['cli_id'], $diasCorredor);
            //
            // // Pertenece a otro corredor
            // if ($vinculado !== null && $vinculado !== $corredor) {
            //     return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece al corredor: ' . $vinculado, null));
            // }
            //
            // // Libre, o ya vinculado a este mismo corredor
            // $mensaje = $vinculado === $corredor
            //     ? 'El cliente ya existe'
            //     : 'El cliente existe y está disponible';
            //
            // return response()->json(RespuestaApi::returnResultado('success', $mensaje, $this->datosFormulario($foto)));
        } catch (QueryException $e) {
            return $this->respuestaErrorFuncion($e, 'Error al consultar el cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }


    // Version 1.0
    // Actualiza el cliente a través de crm.fn_clientes_modificar (las mismas funciones del
    // CRUD del CRM), para que herede sus validaciones, sus defaults y su auditoría forense.
    // Delega en el resolvedor común: el servidor decide crear o modificar según el estado
    // REAL en la base, no según el endpoint que haya llamado el formulario.
    public function updateDynamoCliente(Request $request)
    {
        return $this->guardarClienteDynamo($request);
    }





    // Version 1.0
    // Registra el cliente a través de crm.fn_clientes_registrar (las mismas funciones del
    // CRUD del CRM). Delega en el resolvedor común, igual que updateDynamoCliente: el camino
    // lo decide el estado real en la base, no el endpoint que llamó el formulario.
    public function addDynamoCliente(Request $request)
    {
        return $this->guardarClienteDynamo($request);
    }




    // Version 1.0
    // Consulta de identidad en fuentes oficiales para el formulario público, con la MISMA
    // regla del CRUD del CRM: cédula -> Ecuador Legal, RUC -> SRI, pasaporte no se consulta.
    // Reusa ConsultaIdentidadExternoController tal cual; aquí solo cambia la puerta de entrada
    // (token cifrado del enlace en vez de JWT). La respuesta trae nombres/apellidos ya
    // separados, razonSocial y tipo_sujeto ('N'/'J'), que es lo que el formulario necesita
    // para decidir si muestra "Nombre Empresa".
    public function consultarIdentidadDynamo(Request $request)
    {
        $credenciales = $this->validarTokenEnlace($request);
        if ($credenciales instanceof JsonResponse) {
            return $credenciales;
        }

        $identificacion = trim($request->input('identificacion'));
        $tipoIdentificacion = (int) trim($request->input('tipoidentificacion'));

        if ($identificacion === '') {
            return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
        }

        // El usuario del proveedor viaja en el token: con él, cada consulta del corredor queda
        // registrada en crm.consulta_identidad igual que las que hace un usuario del CRM.
        $usuId = isset($credenciales['usu_id']) ? (int) $credenciales['usu_id'] : null;

        $consultas = app(ConsultaIdentidadExternoController::class);

        if ($tipoIdentificacion === 1) {
            if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
            }

            return $consultas->consultarCedulaEcuadorLegal($identificacion, $usuId);
        }

        if ($tipoIdentificacion === 2) {
            if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
            }

            return $consultas->consultarRucSri($identificacion, $usuId);
        }

        // Pasaporte: no hay fuente que consultar. Se responde el tipo de persona deducido
        // (siempre Natural) para que el formulario no quede a la espera.
        return response()->json(RespuestaApi::returnResultado('error', 'El tipo de identificación no admite consulta externa.', ['tipo_sujeto' => 'N']));
    }




    // ========================================================================
    // GUARDADO VÍA LAS FUNCIONES DEL CRM
    // (crm.fn_clientes_registrar / crm.fn_clientes_modificar, sin modificarlas)
    // ========================================================================

    // Resolvedor único, compartido por addDynamoCliente y updateDynamoCliente. El camino lo
    // decide el estado REAL en la base, no el endpoint que llamó el formulario:
    //   A) no existe la entidad            -> fn_clientes_registrar
    //   B) existe la entidad, sin cliente  -> fn_clientes_registrar (la función la REUSA)
    //   C) ya es cliente tipo 1            -> error "El cliente ya existe", no se toca
    private function guardarClienteDynamo(Request $request)
    {
        // El corredor sale del token cifrado de la URL, NUNCA del formulario.
        $credenciales = $this->validarTokenEnlace($request);
        if ($credenciales instanceof JsonResponse) {
            return $credenciales;
        }

        $corredor = trim($credenciales['corredor']);
        $tipoCorredor = (int) $credenciales['tipo_corredor'];
        $this->usuIdEnlace = isset($credenciales['usu_id']) ? (int) $credenciales['usu_id'] : null;

        // Días de vigencia de la vinculación cliente-corredor (parámetro CLICOR)
        $diasCorredor = $this->obtenerDiasParametroCorredor();
        if ($diasCorredor instanceof JsonResponse) {
            return $diasCorredor;
        }

        $errorValidacion = $this->validarCamposFormulario($request);
        if ($errorValidacion instanceof JsonResponse) {
            return $errorValidacion;
        }

        try {
            $campos = $this->camposFormulario($request);
            $foto = $this->fotoCliente($campos['identificacion'], $campos['tipoidentificacion']);

            if ($foto && !empty($foto['cli_id'])) {
                // Única excepción a "cliente existente no se toca": el que la lupa acabó de crear
                // con GaranCheck para ESTE corredor. Ahí solo se completan email y celular.
                if ($this->consultaQueCreoElCliente($corredor, $campos['identificacion'], (int) $foto['cli_id'])) {
                    return $this->completarContactoCliente($request, $campos, $foto, $corredor);
                }

                return response()->json(RespuestaApi::returnResultado('error', 'El cliente ya existe', null));
                // return $this->modificarClienteDesdeFoto($request, $campos, $foto, $corredor, $tipoCorredor, $diasCorredor);
            }

            // Sin cliente: la lupa no llegó a crearlo (no consultó, o GaranCheck falló).
            return response()->json(RespuestaApi::returnResultado('error', 'Debe consultar la identificación antes de guardar.', null));
            // return $this->registrarClienteContado($request, $campos, $foto, $corredor, $tipoCorredor, $diasCorredor);
        } catch (QueryException $e) {
            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El cliente ya existe', null));
            }

            return $this->respuestaErrorFuncion($e, 'Error al guardar el cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el cliente', $e->getMessage()));
        }
    }

    // ESTADOS A y B. crm.fn_clientes_registrar mide el duplicado contra un CLIENTE tipo 1, no
    // contra la entidad: si la persona ya existe (proveedor, garante) la REUSA y baja su
    // dirección y teléfono anteriores a adicionales. Por eso un solo camino cubre los dos casos.
    private function registrarClienteContado(Request $request, array $campos, ?array $foto, string $corredor, int $tipoCorredor, int $diasCorredor)
    {
        $defaults = $this->defaultsCanal();
        if ($defaults instanceof JsonResponse) {
            return $defaults;
        }

        // Nombre resuelto en el SERVIDOR: los nombres/apellidos que manda el formulario no se
        // usan (el endpoint es público y el body se puede alterar). Ver nombreOficial().
        $oficial = $this->nombreOficial($campos, $foto);
        if ($oficial === null) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo validar la identidad del cliente. Intente nuevamente.', null));
        }
        $campos['nombres'] = $oficial['nombres'];
        $campos['apellidos'] = $oficial['apellidos'];

        return $this->ejecutarGuardado($request, $corredor, $tipoCorredor, $diasCorredor, 'Cliente creado con éxito', function () use ($campos, $foto, $defaults) {
            $payload = [
                'ent_identificacion' => $campos['identificacion'],
                'ent_tipo_identificacion' => $campos['tipoidentificacion'],
                'ent_nombres' => $campos['nombres'],
                'ent_apellidos' => $campos['apellidos'],
                // Email OPCIONAL: si el formulario no lo trae se conserva el de la entidad. En
                // estado B fn_entidad_modificar lo reescribe SIN COALESCE, así que mandar ''
                // le borraría el correo a una persona que ya existía (proveedor, garante).
                'ent_email' => $campos['email'] !== '' ? $campos['email'] : ($foto['ent_email'] ?? ''),
                'ent_nombre_comercial' => trim($campos['apellidos'] . ' ' . $campos['nombres']),
                // Constantes del canal: solo en el alta. En una edición salen de la ficha.
                'pol_id' => $defaults['pol_id'],
                'cat_id' => $defaults['cat_id'],
                'lpr_id' => $defaults['lpr_id'],
                'emp_id' => self::EMP_ID_DEFAULT,
                'cli_credito' => false,
                'tipos_pago' => [['sfp_id' => self::SFP_ID_DEFAULT, 'ctip_default' => true]],
                'direccion' => [
                    'dir_calle_principal' => $campos['direccion'],
                    'dir_calle_secundaria' => $campos['dir_calle_secundaria'],
                    'dir_principal' => true,
                    'dir_activo' => true,
                    'dir_tipo' => self::DIR_TIPO_DEFAULT,
                    // Geo por defecto, igual que el modal del CRM al agregar una dirección nueva.
                    // De aquí la copia fn_cliente_anexo_crear al domicilio, que es su espejo.
                    'dir_prv_id' => $defaults['prv_id'],
                    'dir_ctn_id' => $defaults['ctn_id'],
                    'dir_prq_id' => $defaults['prq_id'],
                ],
                'telefono' => [
                    'tte_id' => self::TTE_ID_CELULAR,
                    'tel_numero' => $campos['telefono'],
                    'tel_principal' => true,
                    'tel_activo' => true,
                ],
                // Si la entidad YA existía (estado B) se arrastran su título y su fecha de
                // nacimiento: fn_entidad_modificar los reescribe SIN COALESCE y este formulario
                // no los captura, así que sin esto se le borrarían a una persona ya registrada.
                'tit_id' => $foto['tit_id'] ?? $defaults['tit_id'],
                'ent_fechanacimiento' => $foto['ent_fechanacimiento'] ?? null,
            ];

            // TIPO DE PERSONA. Lo resuelve el SERVIDOR a partir de la identificación, nunca el
            // formulario: en un endpoint público no se puede confiar en un 'J' que llegue en el
            // request (marcaría como empresa a una cédula). Es la misma regla que usa el CRM
            // cuando el SRI no responde — tercer dígito 9/6 = sociedad, cédula y pasaporte = 'N'.
            //
            // Sin esta clave el payload no llevaba 'dinardap' y fn_cliente_validaciones defaulteaba
            // a 'N', así que TODA empresa nacía marcada como persona natural: al reconsultarla el
            // formulario ya no la reconocía y volvía a mostrar Nombres/Apellidos con el punto.
            $payload['dinardap'] = [
                'cli_tiposujeto' => ValidacionCedulaRucService::tipoSujetoPorIdentificacion(
                    $campos['identificacion'],
                    $campos['tipoidentificacion']
                ) ?: 'N',
            ];

            // Sexo M y estado civil S, los valores con los que nacían los clientes de este
            // canal. Viajan como pane_id porque la función deriva cli_sexo/cli_estadocivil del
            // código DINARDAP de parametro_anexo; omitirlos tomaría el pane_principal del ERP,
            // que es otro valor y dejaría a estos clientes sin comparación con los anteriores.
            $demografico = [];
            if ($defaults['pane_id_sex'] !== null) {
                $demografico['pane_id_sex'] = $defaults['pane_id_sex'];
            }
            if ($defaults['pane_id_eci'] !== null) {
                $demografico['pane_id_eci'] = $defaults['pane_id_eci'];
            }
            if ($demografico) {
                $payload['demografico'] = $demografico;
            }

            return ['crm.fn_clientes_registrar', $payload];
        });
    }

    // Nombre oficial del cliente, resuelto en el servidor y nunca desde el request:
    //   B) la entidad ya existe            -> su ent_nombres / ent_apellidos
    //   A) última fila de crm.consulta_identidad (la deja consultarIdentidadDynamo); si no
    //      hay fila —la fuente falló antes— se reconsulta aquí mismo una vez
    // Devuelve null si no se pudo obtener el nombre: el alta no se hace. El pasaporte no llega
    // aquí (validarCamposFormulario solo admite 1 y 2); si algún día vuelve, no tiene fuente:
    // // if ($campos['tipoidentificacion'] === 3) {
    // //     return ['nombres' => $campos['nombres'], 'apellidos' => $campos['apellidos']];
    // // }
    private function nombreOficial(array $campos, ?array $foto): ?array
    {
        if ($foto && trim((string) ($foto['ent_apellidos'] ?? '')) !== '') {
            return [
                'nombres' => mb_strtoupper(trim((string) ($foto['ent_nombres'] ?? ''))),
                'apellidos' => mb_strtoupper(trim((string) $foto['ent_apellidos'])),
            ];
        }

        $fila = DB::selectOne("SELECT nombres, apellidos, respuesta FROM crm.consulta_identidad
                                WHERE identificacion = ?
                                ORDER BY id DESC LIMIT 1", [$campos['identificacion']]);

        if (!$fila) {
            $consultas = app(ConsultaIdentidadExternoController::class);
            $respuesta = $campos['tipoidentificacion'] === 2
                ? $consultas->consultarRucSri($campos['identificacion'], $this->usuIdEnlace)
                : $consultas->consultarCedulaEcuadorLegal($campos['identificacion'], $this->usuIdEnlace);
            $data = $respuesta->getData(true);

            if (($data['status'] ?? '') !== 'success' || empty($data['data'])) {
                return null;
            }

            $fila = (object) [
                'nombres' => $data['data']['nombres'] ?? null,
                'apellidos' => $data['data']['apellidos'] ?? null,
                'respuesta' => json_encode($data['data']),
            ];
        }

        $respuesta = json_decode((string) $fila->respuesta, true) ?: [];

        // Jurídica: razón social completa en apellidos y '.' en nombres, como guarda este canal.
        if (($respuesta['tipo_sujeto'] ?? 'N') === 'J') {
            $razonSocial = trim((string) ($respuesta['razonSocial'] ?? ''));
            if ($razonSocial === '') {
                $razonSocial = trim((string) $fila->apellidos . ' ' . (string) $fila->nombres);
            }

            return $razonSocial !== '' ? ['nombres' => '.', 'apellidos' => mb_strtoupper($razonSocial)] : null;
        }

        $apellidos = trim((string) $fila->apellidos);
        if ($apellidos === '') {
            return null;
        }

        return ['nombres' => mb_strtoupper(trim((string) $fila->nombres)), 'apellidos' => mb_strtoupper($apellidos)];
    }

    // Completa SOLO email y celular del cliente que la lupa acaba de crear con GaranCheck.
    // Misma hidratación de siempre: se parte de la ficha completa y se pisan dos campos, así
    // no se toca el nombre ni la dirección que trajo el proveedor.
    private function completarContactoCliente(Request $request, array $campos, array $foto, string $corredor)
    {
        return $this->ejecutarGuardadoSinVincular($request, $corredor, 'Cliente guardado con éxito', function () use ($campos, $foto) {
            $payload = $this->asegurarPrincipales($foto, $campos);

            // El email solo se pisa si el corredor lo escribió: fn_entidad_modificar lo reescribe
            // sin COALESCE y mandar '' se lo borraría al que ya tuviera uno.
            if ($campos['email'] !== '') {
                $payload['ent_email'] = $campos['email'];
            }
            $payload['telefono']['tel_numero'] = $campos['telefono'];
            $payload['telefono']['tte_id'] = self::TTE_ID_CELULAR;
            $payload['telefono']['tel_principal'] = true;
            $payload['telefono']['tel_activo'] = true;

            return ['crm.fn_clientes_modificar', $payload];
        });
    }

    // Igual que ejecutarGuardado pero sin vincular: el cliente ya quedó vinculado cuando la
    // consulta de GaranCheck lo creó.
    private function ejecutarGuardadoSinVincular(Request $request, string $corredor, string $mensajeExito, callable $armarPayload)
    {
        DB::transaction(function () use ($request, $corredor, $armarPayload) {
            [$funcion, $payload] = $armarPayload();

            $payload = array_merge($payload, $this->contextoAuditoria($request, $corredor));

            DB::selectOne("SELECT {$funcion}(?::jsonb) AS cli_id", [json_encode($payload)]);
        });

        return response()->json(RespuestaApi::returnResultado('success', $mensajeExito, null));
    }

    // ESTADO C — HIDRATACIÓN. Se parte de la ficha COMPLETA que devuelve el buscador y solo se
    // pisan los campos del formulario. Es obligatorio: crm.fn_clientes_modificar reescribe el
    // cliente entero y solo 14 columnas llevan COALESCE, así que mandar únicamente los 8 campos
    // borraría geo, cónyuge, actividad, datos bancarios y cupo. Además las PKs que trae la foto
    // (dir_id / tel_id / ctip_id / refane_id) son las que evitan que cada guardado DUPLIQUE las
    // filas hijas y cree una dirección principal nueva.
    private function modificarClienteDesdeFoto(Request $request, array $campos, array $foto, string $corredor, int $tipoCorredor, int $diasCorredor)
    {
        $vinculado = $this->corredorVinculado((int) $foto['cli_id'], $diasCorredor);
        if ($vinculado !== null && $vinculado !== $corredor) {
            return response()->json(RespuestaApi::returnResultado('error', 'El cliente ya existe', null));
        }

        return $this->ejecutarGuardado($request, $corredor, $tipoCorredor, $diasCorredor, 'Cliente actualizado con éxito', function () use ($campos, $foto) {
            // Clientes legacy sin dirección o teléfono principal: la función aborta con
            // CLIENTE_NO_ENCONTRADO. Se crean al vuelo (mismo criterio que el formulario de
            // corredores ALM) para no dejarlos fuera. Va dentro de la transacción.
            $payload = $this->asegurarPrincipales($foto, $campos);

            // Los campos del formulario, y nada más. Todo lo demás viaja con el valor que ya
            // tenía en la base, así que se reescribe idéntico.
            $payload['ent_nombres'] = $campos['nombres'];
            $payload['ent_apellidos'] = $campos['apellidos'];
            // Email OPCIONAL: solo se pisa si el formulario trae uno. El payload ya arrastra el
            // de la foto, y fn_entidad_modificar lo reescribe sin COALESCE: mandar '' se lo
            // borraría en silencio a un cliente que sí lo tenía.
            if ($campos['email'] !== '') {
                $payload['ent_email'] = $campos['email'];
            }
            $payload['ent_nombre_comercial'] = trim($campos['apellidos'] . ' ' . $campos['nombres']);
            $payload['direccion']['dir_calle_principal'] = $campos['direccion'];
            $payload['direccion']['dir_calle_secundaria'] = $campos['dir_calle_secundaria'];
            $payload['telefono']['tel_numero'] = $campos['telefono'];

            // ent_identificacion y ent_tipo_identificacion se dejan TAL COMO VIENEN DE LA FOTO,
            // a propósito: fn_entidad_modificar los reescribe sin COALESCE, y como la búsqueda
            // casa por los 10 primeros dígitos, un cliente guardado con RUC quedaría con la
            // cédula si el corredor la teclea. Aquí la identificación solo sirve para buscar.

            return ['crm.fn_clientes_modificar', $payload];
        });
    }

    // Ejecuta la función PG y vincula al corredor en la MISMA transacción: si la vinculación
    // falla, el cliente también se revierte. El nombre de la función se arma en el código,
    // nunca desde el request.
    private function ejecutarGuardado(Request $request, string $corredor, int $tipoCorredor, int $diasCorredor, string $mensajeExito, callable $armarPayload)
    {
        DB::transaction(function () use ($request, $corredor, $tipoCorredor, $diasCorredor, $armarPayload) {
            [$funcion, $payload] = $armarPayload();

            $payload = array_merge($payload, $this->contextoAuditoria($request, $corredor));

            $resultado = DB::selectOne("SELECT {$funcion}(?::jsonb) AS cli_id", [json_encode($payload)]);

            $this->vincularCorredor((int) $resultado->cli_id, $corredor, $tipoCorredor, $diasCorredor);
        });

        return response()->json(RespuestaApi::returnResultado('success', $mensajeExito, null));
    }

    // Trazabilidad del canal público: aquí no hay usuario del CRM, así que el autor es el
    // corredor del token. 'usuario_auditoria' alimenta cliente.created_by/updated_by (lo que
    // muestra el resumen del modal) y el bloque 'auditoria' va a la auditoría forense.
    // Los DOS caminos lo mandan: si el update lo omitiera, updated_by quedaría en NULL.
    private function contextoAuditoria(Request $request, string $corredor): array
    {
        return [
            'usuario_auditoria' => mb_substr('CORREDOR - ' . $corredor, 0, 100),
            'auditoria' => [
                // Usuario del proveedor que generó el enlace (del token). Con enlaces viejos
                // que no lo traen queda null, como antes.
                'usuario_id' => $this->usuIdEnlace,
                'usuario_login' => mb_substr($corredor, 0, 100),
                'usuario_nombre' => 'MULTINIVEL',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => (string) Str::uuid(),
            ],
        ];
    }

    // La ficha completa del cliente, desde la MISMA función que usa el CRUD del CRM.
    // Su FROM es entidad LEFT JOIN cliente ... AND cli_tipocli = 1, así que también devuelve a
    // una persona que existe pero todavía no es cliente (con cli_id en NULL).
    private function fotoCliente(string $identificacion, int $tipoIdentificacion): ?array
    {
        $fila = DB::selectOne('SELECT datos FROM crm.fn_cliente_buscar_por_identificacion(?, ?)', [$identificacion, $tipoIdentificacion]);

        if (!$fila || empty($fila->datos)) {
            return null;
        }

        return json_decode($fila->datos, true);
    }

    // Lo ÚNICO que sale al navegador del corredor. La foto trae la ficha completa (geo,
    // cónyuge, actividad, cupo, referencias, datos bancarios); de aquí solo salen los campos
    // que el formulario muestra. El resto nunca cruza la red.
    private function datosFormulario(array $foto): array
    {
        return [
            'ent_nombres' => $foto['ent_nombres'] ?? '',
            'ent_apellidos' => $foto['ent_apellidos'] ?? '',
            'ent_email' => $foto['ent_email'] ?? '',
            'ent_tipo_identificacion' => $foto['ent_tipo_identificacion'] ?? null,
            'tel_numero' => $foto['telefono']['tel_numero'] ?? '',
            'dir_calle_principal' => $foto['direccion']['dir_calle_principal'] ?? '',
            'dir_calle_secundaria' => $foto['direccion']['dir_calle_secundaria'] ?? '',
            // Para que el formulario muestre "Nombre Empresa" en vez de Nombres/Apellidos.
            'cli_tiposujeto' => $foto['dinardap']['cli_tiposujeto'] ?? 'N',
            // Throttle de la consulta a fuentes externas, resuelto por la misma función.
            'debe_consultar_identidad' => $foto['debe_consultar_identidad'] ?? true,
        ];
    }

    // Normalización única de los campos del formulario (mayúsculas / minúsculas / trim).
    private function camposFormulario(Request $request): array
    {
        return [
            'identificacion' => trim($request->input('identificacion')),
            'tipoidentificacion' => (int) trim($request->input('tipoidentificacion')),
            'nombres' => mb_strtoupper(trim($request->input('nombres'))),
            'apellidos' => mb_strtoupper(trim($request->input('apellidos'))),
            // ?? '' porque el middleware global ConvertEmptyStringsToNull convierte el '' del
            // formulario en null, y trim(null) es deprecation desde PHP 8.1.
            'email' => mb_strtolower(trim($request->input('email') ?? '')),
            'telefono' => trim($request->input('telefono')),
            'direccion' => mb_strtoupper(trim($request->input('direccion') ?? '')),
            'dir_calle_secundaria' => mb_strtoupper(trim($request->input('dir_calle_secundaria') ?? '')),
        ];
    }

    // Devuelve JsonResponse si algo falla, null si todo está bien.
    private function validarCamposFormulario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Solo cédula (1) y RUC (2): sin pasaporte, que no tiene fuente oficial del nombre.
            'tipoidentificacion' => 'required|in:1,2',
            'identificacion' => 'required|string',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            // OPCIONAL: nullable deja pasar vacío o ausente, pero si trae algo exige formato.
            'email' => 'nullable|email',
            'telefono' => 'required|digits:10',
            'direccion' => 'required|string',
            'dir_calle_secundaria' => 'required|string',
        ], [
            'tipoidentificacion.in' => 'Solo se admite cédula o RUC.',
            'email.email' => 'Ingrese un email válido.',
            'telefono.digits' => 'El teléfono debe tener 10 dígitos.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        // El dígito verificador lo revalida la función PG, pero validarlo aquí da un mensaje
        // específico en vez del genérico IDENTIFICACION_INVALIDA. El pasaporte (tipo 3) no
        // tiene algoritmo: la función lo acepta tal cual.
        $identificacion = trim($request->input('identificacion'));
        $tipo = trim($request->input('tipoidentificacion'));

        if ($tipo == 1 && !ValidacionCedulaRucService::esCedulaValida($identificacion)) {
            return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
        }

        if ($tipo == 2 && !ValidacionCedulaRucService::esRucValido($identificacion)) {
            return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
        }

        return null;
    }

    // Valores propios del canal STS, SOLO para el alta. Se resuelven igual que el camino PHP
    // anterior ('clien' y parámetros TIT/LPR) para no cambiar el dato de los clientes nuevos.
    private function defaultsCanal()
    {
        $politica = DB::selectOne("SELECT pol_id FROM politica WHERE pol_nombre = 'CONTADO' AND pol_tipocli = 1");
        if (!$politica) {
            return response()->json(RespuestaApi::returnResultado('error', 'No está configurada la política CONTADO, comuniquese con el administrador.', null));
        }

        // Título por defecto (parámetro TIT/CLI); la función NO lo defaultea
        $titulo = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id
                                    FROM parametro
                                    WHERE par_abreviacion='TIT'
                                        AND mod_abreviatura='CLI'
                                    LIMIT 1");

        $categoria = DB::selectOne("SELECT cat_id
                                    FROM catcliente
                                    WHERE cat_abreviacion = 'clien'");

        $listaPre = DB::selectOne("SELECT lpr_id
                                    FROM listapre
                                    WHERE lpr_nombre
                                        IN (SELECT par_texto
                                                FROM parametro
                                                WHERE par_abreviacion='LPR'
                                                    AND mod_abreviatura='CLI'
                                                LIMIT 1)
                                    LIMIT 1");

        // Grupos DINARDAP: 2 = SEXO, 3 = ESTADO CIVIL.
        $sexo = DB::selectOne("SELECT pane_id FROM parametro_anexo
                                WHERE pane_grupo_codigo = 2 AND pane_cod_dinardap = ? LIMIT 1", [self::CLI_SEXO_DEFAULT]);

        $estadoCivil = DB::selectOne("SELECT pane_id FROM parametro_anexo
                                        WHERE pane_grupo_codigo = 3 AND pane_cod_dinardap = ? LIMIT 1", [self::CLI_ESTADOCIVIL_DEFAULT]);

        // Geo por defecto de la dirección: MISMA regla que el modal del CRM
        // (ClienteController::catalogos, bloque defaults) — provincia AZUAY + primer cantón ACTIVO
        // alfabético + primera parroquia ACTIVA de ese cantón. El formulario del corredor no captura
        // la geo, así que sin esto el cliente nacía sin provincia/cantón/parroquia.
        //
        // El filtro por *_activo NO es opcional: sin él el primer cantón alfabético de AZUAY es
        // ASUNCION, que está inactivo (de los 225 que se desactivaron por ser parroquias disfrazadas
        // de cantón), y su única parroquia es el marcador "SIN PARROQUIAS".
        $provinciaDefecto = "(SELECT prv_id FROM provincia WHERE UPPER(TRIM(prv_nombre)) = 'AZUAY' AND prv_activo = true LIMIT 1)";
        $cantonDefecto = "(SELECT ctn_id FROM canton WHERE prv_id = {$provinciaDefecto} AND ctn_activo = true ORDER BY ctn_nombre LIMIT 1)";

        $provincia = DB::selectOne("SELECT {$provinciaDefecto} AS prv_id");
        $canton = DB::selectOne("SELECT {$cantonDefecto} AS ctn_id");
        $parroquia = DB::selectOne("SELECT prq_id FROM parroquia
                                     WHERE ctn_id = {$cantonDefecto} AND prq_activo = true
                                     ORDER BY prq_nombre LIMIT 1");

        return [
            'pol_id' => $politica->pol_id,
            'tit_id' => $titulo->tit_id ?? null,
            'cat_id' => $categoria->cat_id ?? null,
            'lpr_id' => $listaPre->lpr_id ?? null,
            'pane_id_sex' => $sexo->pane_id ?? null,
            'pane_id_eci' => $estadoCivil->pane_id ?? null,
            'prv_id' => $provincia->prv_id ?? null,
            'ctn_id' => $canton->ctn_id ?? null,
            'prq_id' => $parroquia->prq_id ?? null,
        ];
    }

    // Un cliente sin dirección o teléfono principal hace abortar a fn_clientes_modificar.
    // Se crean con los datos del formulario y se apuntan en la entidad; la función los
    // actualizará enseguida con esos mismos valores.
    private function asegurarPrincipales(array $foto, array $campos): array
    {
        $entId = (int) $foto['ent_id'];

        if (empty($foto['direccion']['dir_id'])) {
            $nuevaDireccion = new Direccion();
            $nuevaDireccion->dir_calle_principal = $campos['direccion'];
            $nuevaDireccion->dir_calle_secundaria = $campos['dir_calle_secundaria'];
            $nuevaDireccion->dir_tipo = self::DIR_TIPO_DEFAULT;
            $nuevaDireccion->dir_principal = true;
            $nuevaDireccion->dir_activo = true;
            $nuevaDireccion->save();

            DB::update("UPDATE public.entidad SET ent_direccion_principal = ? WHERE ent_id = ?", [$nuevaDireccion->dir_id, $entId]);

            $foto['direccion'] = array_merge(
                is_array($foto['direccion'] ?? null) ? $foto['direccion'] : [],
                ['dir_id' => $nuevaDireccion->dir_id, 'dir_principal' => true, 'dir_activo' => true, 'dir_tipo' => self::DIR_TIPO_DEFAULT]
            );
        }

        if (empty($foto['telefono']['tel_id'])) {
            $nuevoTelefono = new Telefono();
            $nuevoTelefono->tte_id = self::TTE_ID_CELULAR;
            $nuevoTelefono->tel_numero = $campos['telefono'];
            $nuevoTelefono->tel_principal = true;
            $nuevoTelefono->tel_activo = true;
            $nuevoTelefono->save();

            DB::update("UPDATE public.entidad SET ent_telefono_principal = ? WHERE ent_id = ?", [$nuevoTelefono->tel_id, $entId]);

            $foto['telefono'] = array_merge(
                is_array($foto['telefono'] ?? null) ? $foto['telefono'] : [],
                ['tel_id' => $nuevoTelefono->tel_id, 'tte_id' => self::TTE_ID_CELULAR, 'tel_principal' => true, 'tel_activo' => true]
            );
        }

        return $foto;
    }

    // Log de la consulta del corredor: una fila por cada clic en la lupa, exista o no el cliente.
    // Sin auditoría forense: la tabla ya guarda corredor + identificación + fecha.
    // $proveedor: a quién se consultó. NULL cuando el cliente ya existía y no se consultó a nadie.
    private function registrarConsultaCorredor(string $corredor, string $identificacion, ?int $cliId, bool $existia, ?string $proveedor = null): ClientesMultinivelConsulta
    {
        $consulta = new ClientesMultinivelConsulta();
        $consulta->corredor = $corredor;
        $consulta->identificacion = $identificacion;
        $consulta->cli_id = $cliId;
        $consulta->existia = $existia;
        $consulta->proveedor = $proveedor;
        $consulta->save();

        return $consulta;
    }

    // Permiso para completar email y celular de un cliente que YA existe porque la lupa acaba de
    // crearlo con GaranCheck. Sale de la ÚLTIMA consulta de este corredor para esa identificación:
    // si es la que lo creó (existia = false) y apunta al mismo cli_id, se deja actualizar.
    // Nunca se acepta un cli_id del navegador: el endpoint es público.
    private function consultaQueCreoElCliente(string $corredor, string $identificacion, int $cliId): bool
    {
        $fila = DB::selectOne("SELECT existia, cli_id
                                 FROM public.clientes_multinivel_consultas
                                WHERE corredor = ? AND identificacion = ?
                                ORDER BY id DESC LIMIT 1", [$corredor, $identificacion]);

        return $fila && $fila->existia === false && (int) $fila->cli_id === $cliId;
    }

    // Corredor con vinculación VIGENTE, o null si está libre. Si la vinculación ya cumplió los
    // días del parámetro CLICOR se desvincula y el cliente pasa a considerarse libre.
    private function corredorVinculado(int $cliId, int $diasCorredor): ?string
    {
        $fila = DB::selectOne("SELECT corredor FROM public.clientes_multinivel
                                WHERE cli_id = ?
                                    AND activo = true
                                    AND fecha_desvinculacion IS NULL
                                LIMIT 1", [$cliId]);

        if (!$fila) {
            return null;
        }

        if ($this->desvincularCorredorSiExpiro($cliId, $diasCorredor)) {
            return null;
        }

        return $fila->corredor;
    }

    // Vincula el cliente al corredor del token si está libre. Si ya tiene vinculación vigente
    // no hace nada: el caso "pertenece a otro corredor" se rechaza antes de llegar aquí.
    private function vincularCorredor(int $cliId, string $corredor, int $tipoCorredor, int $diasCorredor): void
    {
        $vigente = DB::selectOne("SELECT cli_id FROM public.clientes_multinivel
                                    WHERE cli_id = ?
                                        AND activo = true
                                        AND fecha_desvinculacion IS NULL
                                    LIMIT 1", [$cliId]);

        if ($vigente) {
            return;
        }

        $clienteMultinivel = new ClientesMultinivel();
        $clienteMultinivel->cli_id = $cliId;
        $clienteMultinivel->corredor = $corredor;
        $clienteMultinivel->tipo_corredor = $tipoCorredor;
        $clienteMultinivel->dias_parametro = $diasCorredor;
        $clienteMultinivel->activo = true;
        $clienteMultinivel->save();
    }

    // Traduce el código de excepción de las funciones PG al mensaje del corredor. El orden de
    // MENSAJES_ERROR importa: los códigos largos van antes que los que son su subcadena
    // (REQUIERE_TIPOEMPRESA antes que EMPRESA).
    private function respuestaErrorFuncion(QueryException $e, string $mensajeGenerico)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, $e->getMessage()));
    }




    // Version 1.0
    public function generarLinkFormulario(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'corredor' => 'required|string',
                // La identificación YA NO viaja en el enlace (decisión del 22/Sep): el corredor
                // la teclea en el formulario. Si se reactiva, descomentar aquí, en el token y
                // en validarEnlace, más el patchValue del front.
                // 'identificacion' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Validación datos incorrectos', $validator->errors()));
            }

            $corredor = trim($request->input('corredor'));

            // $identificacion = trim($request->input('identificacion'));
            // if (!preg_match('/^\d{10}$/', $identificacion) && !preg_match('/^\d{13}$/', $identificacion)) {
            //     return response()->json(RespuestaApi::returnResultado('error', 'La identificación debe tener 10 dígitos (cédula) o 13 (RUC).', null));
            // }

            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'URL-FRONTEND')
                ->first();

            if (!$parametro || empty($parametro->valor)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No está configurado el parámetro URL-FRONTEND.', null));
            }

            // valor = "base,/ruta?t=,minutos"
            // [0] URL del frontend, [1] ruta del formulario, [2] minutos de vigencia
            $partes = explode(',', $parametro->valor);

            $ruta = isset($partes[1]) ? trim($partes[1]) : '';
            if ($ruta === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'No está configurada la ruta del formulario en el parámetro URL-FRONTEND.', null));
            }

            $minutos = isset($partes[2]) ? (int) trim($partes[2]) : 0;
            if ($minutos < 1) {
                return response()->json(RespuestaApi::returnResultado('error', 'No está configurado el tiempo de duración en el parámetro URL-FRONTEND.', null));
            }

            if ($parametro->activar == true) {
                $base = rtrim(trim($partes[0]), '/');
            } else {
                $base = 'http://192.168.1.142:4201';
            }

            $expires = now()->addMinutes($minutos)->timestamp;

            // Credenciales cifrado y autenticado: nadie puede leerlo ni manipularlo.
            // tipo_corredor va QUEMADO aquí (1. Corredor ALM; 2. Corredor STS;): todo link
            // de este endpoint es tipo 2 (STS); ni el front ni el proveedor lo envían nunca.
            // El tipo 1 queda reservado para el flujo interno del CRM (sin link).
            //
            // usu_id: el usuario del proveedor que pidió el link. Esta ruta SÍ tiene JWT, y las
            // del formulario NO, así que es el único punto donde se puede saber quién habilitó
            // ese acceso. Viaja dentro del token para que las consultas de identidad y la
            // auditoría del cliente queden atribuidas a él. Los enlaces emitidos antes de este
            // cambio no lo traen: se lee como opcional.
            $t = Crypt::encryptString(json_encode([
                'corredor' => $corredor,
                'tipo_corredor' => 2,
                'usu_id' => auth('api')->id(),
                // 'identificacion' => $identificacion,   // ver generarLinkFormulario
                'expires' => $expires,
            ]));

            $url = $base . $ruta . urlencode($t);

            // Solo se devuelve la URL
            return response()->json(RespuestaApi::returnResultado('success', 'Link generado con éxito', ['url' => $url,]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }




    // Version 1.0
    // Valida únicamente el token del enlace
    public function validarEnlace(Request $request)
    {
        try {
            // Descifrar y validar el token del enlace (caducidad + integridad)
            $credenciales = $this->validarTokenEnlace($request);

            if ($credenciales instanceof JsonResponse) {
                return $credenciales;
            }

            // El enlace ya no lleva la identificación: el corredor la teclea en el formulario.
            // $identificacion = trim((string) ($credenciales['identificacion'] ?? ''));
            // return response()->json(RespuestaApi::returnResultado('success', 'Enlace válido', [
            //     'identificacion' => $identificacion !== '' ? $identificacion : null,
            //     'tipoidentificacion' => strlen($identificacion) === 13 ? '2' : '1',
            // ]));

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace válido', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }




    // Version 1.0
    // Lee el parámetro CLICOR (días de vigencia de la vinculación cliente-corredor)
    // y devuelve su valor como entero. Si no está configurado o no es un número
    // válido, devuelve un JsonResponse de error.
    private function obtenerDiasParametroCorredor()
    {
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'CLICOR')
            ->first();

        $valor = $parametro ? trim((string) $parametro->valor) : '';

        // Error si el parámetro no existe, su valor es NULL/vacío o no es un entero.
        // Se acepta 0 o más días (0 = la vinculación expira de inmediato).
        //
        // ctype_digit en vez de (int) >= 0: la comparación anterior daba por bueno cualquier texto,
        // porque (int) 'abc' es 0 — y 0 días significa "expira ya", así que un valor mal escrito
        // desvinculaba del corredor a TODOS los clientes en cada consulta, sin avisar.
        if ($valor === '' || !ctype_digit($valor)) {
            return response()->json(RespuestaApi::returnResultado('error', 'El parámetro CLICOR no tiene un número de días válido, comuníquese con el administrador.', null));
        }

        return (int) $valor;
    }




    // Version 1.0
    // Detecta la violación del índice único parcial uq_clientes_multinivel_vigente
    // (SQLSTATE 23505): otro corredor vinculó al mismo cliente en paralelo
    // (carrera entre el SELECT de verificación y el COMMIT del guardado).
    private function esCarreraVinculacion(Exception $e): bool
    {
        return $e instanceof QueryException
            && ($e->errorInfo[0] ?? null) === '23505'
            && strpos($e->getMessage(), 'uq_clientes_multinivel_vigente') !== false;
    }




    // Version 1.0
    // Si la vinculación vigente del cliente (activo = true y fecha_desvinculacion NULL)
    // ya cumplió los días del parámetro CLICOR (created_at + días <= hoy), la desactiva:
    // activo = false + fecha_desvinculacion = NOW().
    // Devuelve true si se desvinculó (el cliente queda libre para un nuevo corredor).
    private function desvincularCorredorSiExpiro($cliId, int $dias): bool
    {
        $filas = DB::update("UPDATE public.clientes_multinivel
                                SET activo = false,
                                    fecha_desvinculacion = NOW(),
                                    updated_at = NOW()
                            WHERE cli_id = ?
                                AND activo = true
                                AND fecha_desvinculacion IS NULL
                                AND created_at <= ?", [$cliId, now()->subDays($dias)]);

        return $filas > 0;
    }




    // Descifra y valida el token ?t= del enlace (caducidad + integridad).
    // Devuelve el array de las credenciales (['corredor' => ..., 'tipo_corredor' => ..., 'expires' => ...])
    // Metodo privado que se usa para validar si la url es correcta en el metodo addDynamoCliente
    private function validarTokenEnlace(Request $request)
    {
        $t = (string) $request->input('t');

        if ($t === '') {
            return response()->json(RespuestaApi::returnResultado('error', 'Enlace no válido.', null));
        }

        try {
            $credenciales = json_decode(Crypt::decryptString($t), true);
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Enlace no válido.', null));
        }

        if (!is_array($credenciales) || empty($credenciales['corredor']) || empty($credenciales['tipo_corredor']) || empty($credenciales['expires'])) {
            return response()->json(RespuestaApi::returnResultado('error', 'Enlace no válido.', null));
        }

        if ((int) $credenciales['expires'] < now()->timestamp) {
            return response()->json(RespuestaApi::returnResultado('error', 'El enlace ha expirado. Solicite uno nuevo.', null));
        }

        return $credenciales;
    }







    // Version 1.0
    // LISTADO GENERAL DE CLIENTES CON EL RESPECTIVO CORREDOR
    // Devuelve las vinculaciones VIGENTES (activo = true y fecha_desvinculacion NULL)
    // con los datos del cliente, filtradas por rango de fecha de registro
    // (BETWEEN sobre cm.created_at). fecha_inicio y fecha_fin son requeridas (YYYY-MM-DD).
    public function listClientesCorredor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            ], [
                'fecha_inicio.required' => 'Debe ingresar la fecha de inicio.',
                'fecha_inicio.date' => 'La fecha de inicio no es válida.',
                'fecha_fin.required' => 'Debe ingresar la fecha de fin.',
                'fecha_fin.date' => 'La fecha de fin no es válida.',
                'fecha_fin.after_or_equal' => 'La fecha de fin debe ser mayor o igual a la fecha de inicio.',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
            }

            $fechaInicio = trim($request->input('fecha_inicio'));
            $fechaFin = trim($request->input('fecha_fin'));

            // created_at es timestamp: se castea a date para que el BETWEEN
            // incluya los registros de todo el día de fechaFin
            $resultado = DB::select("SELECT
                                        cm.corredor,
                                        cm.created_at AS fecha_registro,
                                        c.cli_codigo AS identificacion,
                                        e.ent_nombres AS nombres,
                                        e.ent_apellidos AS apellidos,
                                        e.ent_email AS email,
                                        t.tel_numero AS telefono,
                                        d.dir_calle_principal AS calle_principal,
                                        d.dir_calle_secundaria AS calle_secundaria,
                                        NULL AS calificacion_cliente
                                    FROM public.clientes_multinivel cm
                                        JOIN public.cliente c ON c.cli_id = cm.cli_id
                                        JOIN public.entidad e ON e.ent_id = c.ent_id
                                        LEFT JOIN public.telefono t ON t.tel_id = e.ent_telefono_principal
                                        LEFT JOIN public.direccion d ON d.dir_id = e.ent_direccion_principal
                                    WHERE cm.activo = true
                                        AND cm.fecha_desvinculacion IS NULL
                                        AND cm.created_at::date BETWEEN ? AND ?
                                    ORDER BY cm.created_at ASC", [$fechaInicio, $fechaFin]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Version 1.0
    // Obtener clientes de un corredor
    public function listCorredorClientePaginado(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'corredor' => 'required|string',
                'identificacion_cliente' => 'nullable|string',
                'num_pagina' => 'required|integer|min:1',
            ], [
                'corredor.required' => 'Debe ingresar un corredor',
                'corredor.string' => 'Corredor inválido',
                'identificacion_cliente.string' => 'Identificación de cliente inválida',
                'num_pagina.required' => 'Debe ingresar el número de página',
                'num_pagina.integer' => 'Número de página inválido',
                'num_pagina.min' => 'El número de página debe ser mayor o igual a 1',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
            }

            $corredor = trim($request->input('corredor', ''));
            $identificacion_cliente = trim($request->input('identificacion_cliente', ''));
            $num_pagina = (int) $request->input('num_pagina');

            $resultado = DB::select(
                "SELECT * FROM crm.fn_cliente_corredor_listar_paginacion(?, ?, ?)",
                [$corredor, $identificacion_cliente, $num_pagina]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
