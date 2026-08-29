<?php

namespace App\Http\Controllers\corredores;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Direccion;
use App\Models\openceo\Telefono;
use App\Models\sts\ClientesMultinivel;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

// Formulario de clientes para corredores INTERNOS del CRM (Corredor ALM).
// Mismo flujo del formulario público STS pero SIN link cifrado: la identidad sale del
// usuario JWT (etiqueta "usu_alias - APELLIDOS NOMBRES") y tipo_corredor va QUEMADO en 1
// (1. Corredor ALM; 2. Corredor STS;).
//
// TODO el guardado pasa por las funciones del CRUD del CRM —crm.fn_clientes_registrar y
// crm.fn_clientes_modificar, sin modificarlas— para heredar sus validaciones, sus defaults
// y su auditoría forense. Ver crm/PLANES/STS-MULTINIVEL/STS-CLIENTE-FUNCIONES-CRM.md.
class CorredorClienteController extends Controller
{
    private const TIPO_CORREDOR_ALM = 1;
    private const EMP_ID_DEFAULT = 1;
    private const SFP_ID_EFECTIVO = 1;
    private const TTE_ID_CELULAR = 2;
    // Tipo de dirección por defecto del formulario (catálogo crm.fn_tipo_direccion_listar: CASA / TRABAJO)
    private const DIR_TIPO_DEFAULT = 'CASA';

    // Un cliente a CRÉDITO conserva su política al editarse desde aquí (nunca se degrada a
    // contado), pero eso activa las validaciones de crédito de crm.fn_cliente_validaciones.
    // Si le faltan datos que este formulario no captura, se indica dónde se resuelven.
    private const MSG_CLIENTE_INCOMPLETO_CREDITO = 'Este cliente tiene datos pendientes; complételos en el módulo de clientes del CRM.';

    // Mensaje amigable para cada excepción que pueden lanzar crm.fn_clientes_registrar y
    // crm.fn_clientes_modificar (ambas validan por crm.fn_cliente_validaciones, así que
    // comparten el catálogo de códigos).
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

    // Version 1.0
    // Consulta la identificación y resuelve los 4 escenarios (igual que el
    // formulario público STS, pero el corredor es el usuario logueado del CRM).
    public function verificarClienteCorredor(Request $request)
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

            $corredor = $this->etiquetaCorredor();
            if ($corredor === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario.', null));
            }

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
            }

            // La foto sale de la MISMA función que usa el CRUD del CRM
            // (crm.fn_cliente_buscar_por_identificacion). Busca sobre public.entidad con
            // LEFT JOIN cliente ... AND cli_tipocli = 1, así que a diferencia del SELECT
            // anterior —que miraba cliente.cli_codigo— también encuentra a una persona que
            // YA existe en el sistema pero todavía no es cliente (p.ej. un proveedor).
            $foto = $this->fotoCliente($identificacion, (int) $tipoidentificacion);

            // ESTADO A: no existe ni la entidad
            if (!$foto) {
                return response()->json(RespuestaApi::returnResultado('success', 'El cliente no existe', null));
            }

            // ESTADO B: la persona existe pero aún no es cliente. No puede tener vinculación
            // (esa tabla cuelga de cli_id), así que está libre por definición.
            if (empty($foto['cli_id'])) {
                return response()->json(RespuestaApi::returnResultado('success', 'La persona ya está registrada. Verifique los datos para registrarla como cliente.', $this->datosFormulario($foto)));
            }

            // ESTADO C: ya es cliente. Si la vinculación vigente cumplió los días pactados
            // se desvincula y el cliente se evalúa como libre.
            $vinculado = $this->corredorVinculado((int) $foto['cli_id'], $diasCorredor);

            // Pertenece a otro corredor
            if ($vinculado !== null && $vinculado !== $corredor) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece al corredor: ' . $vinculado, null));
            }

            // Libre, o ya vinculado a este mismo corredor
            $mensaje = $vinculado === $corredor
                ? 'El cliente ya existe'
                : 'El cliente existe y está disponible';

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $this->datosFormulario($foto)));
        } catch (QueryException $e) {
            return $this->respuestaErrorFuncion($e, 'Error al consultar el cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al consultar el cliente', $e->getMessage()));
        }
    }

    // Version 1.0
    // Guarda el cliente decidiendo el camino en el servidor (no confía en el front):
    // - cliente existe  -> actualiza datos + vincula si está libre
    // - cliente no existe y entidad tampoco -> crm.fn_clientes_registrar (contado) + vincula
    // - cliente no existe pero entidad sí   -> camino PHP (reusa la entidad) + vincula
    public function guardarClienteCorredor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipoidentificacion' => 'required',
                'identificacion' => 'required|string',
                'nombres' => 'required|string',
                'apellidos' => 'required|string',
                'email' => 'required|email',
                'telefono' => 'required|digits:10',
                'direccion' => 'required|string',
                'dir_calle_secundaria' => 'required|string',
            ], [
                'email.email' => 'Ingrese un email válido.',
                'telefono.digits' => 'El teléfono debe tener 10 dígitos.',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
            }

            $corredor = $this->etiquetaCorredor();
            if ($corredor === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario.', null));
            }

            // Días de vigencia de la vinculación cliente-corredor (parámetro CLICOR)
            $diasCorredor = $this->obtenerDiasParametroCorredor();
            if ($diasCorredor instanceof JsonResponse) {
                return $diasCorredor;
            }

            $tipoidentificacion = trim($request->input('tipoidentificacion'));
            $identificacion = trim($request->input('identificacion'));
            $nombres = mb_strtoupper(trim($request->input('nombres')));
            $apellidos = mb_strtoupper(trim($request->input('apellidos')));
            $email = mb_strtolower(trim($request->input('email')));
            $telefono = trim($request->input('telefono'));
            $direccion = mb_strtoupper(trim($request->input('direccion')));
            $direccionSecundaria = mb_strtoupper(trim($request->input('dir_calle_secundaria')));

            if ($tipoidentificacion == 1) {
                if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
                }
            } elseif ($tipoidentificacion == 2) {
                if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
                }
            }

            $datos = [
                'identificacion' => $identificacion,
                'tipoidentificacion' => $tipoidentificacion,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'email' => $email,
                'telefono' => $telefono,
                'direccion' => $direccion,
                'direccionSecundaria' => $direccionSecundaria,
            ];

            // El camino lo decide el estado REAL en la base:
            //   A) no existe la entidad            -> fn_clientes_registrar
            //   B) existe la entidad, sin cliente  -> fn_clientes_registrar (la función la REUSA)
            //   C) ya es cliente tipo 1            -> fn_clientes_modificar con la ficha hidratada
            $foto = $this->fotoCliente($identificacion, (int) $tipoidentificacion);

            if ($foto && !empty($foto['cli_id'])) {
                return $this->modificarClienteDesdeFoto($request, $datos, $foto, $corredor, $diasCorredor);
            }

            return $this->registrarClienteContado($request, $datos, $foto, $corredor, $diasCorredor);
        } catch (QueryException $e) {
            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
            }

            return $this->respuestaErrorFuncion($e, 'Error al guardar el cliente');
        } catch (Exception $e) {
            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el cliente', $e->getMessage()));
        }
    }

    // ESTADOS A y B. crm.fn_clientes_registrar mide el duplicado contra un CLIENTE tipo 1, no
    // contra la entidad: si la persona ya existe (proveedor, garante) la REUSA y baja su
    // dirección y teléfono anteriores a adicionales. Por eso un solo camino cubre los dos casos.
    private function registrarClienteContado(Request $request, array $datos, ?array $foto, string $corredor, int $diasCorredor)
    {
        $defaults = $this->defaultsCanal();
        if ($defaults instanceof JsonResponse) {
            return $defaults;
        }

        return $this->ejecutarGuardado($request, $corredor, $diasCorredor, 'Cliente creado con éxito', function () use ($datos, $foto, $defaults) {
            return ['crm.fn_clientes_registrar', [
                'ent_identificacion' => $datos['identificacion'],
                'ent_tipo_identificacion' => (int) $datos['tipoidentificacion'],
                'ent_nombres' => $datos['nombres'],
                'ent_apellidos' => $datos['apellidos'],
                'ent_email' => $datos['email'],
                'ent_nombre_comercial' => trim($datos['apellidos'] . ' ' . $datos['nombres']),
                'pol_id' => $defaults['pol_id'],
                'emp_id' => self::EMP_ID_DEFAULT,
                'cli_credito' => false,
                'tipos_pago' => [['sfp_id' => self::SFP_ID_EFECTIVO, 'ctip_default' => true]],
                'direccion' => [
                    'dir_calle_principal' => $datos['direccion'],
                    'dir_calle_secundaria' => $datos['direccionSecundaria'],
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
                    'tel_numero' => $datos['telefono'],
                    'tel_principal' => true,
                    'tel_activo' => true,
                ],
                // Si la entidad YA existía (estado B) se arrastran su título y su fecha de
                // nacimiento: fn_entidad_modificar los reescribe SIN COALESCE y este formulario
                // no los captura, así que sin esto se le borrarían a una persona ya registrada.
                'tit_id' => $foto['tit_id'] ?? $defaults['tit_id'],
                'ent_fechanacimiento' => $foto['ent_fechanacimiento'] ?? null,
                // TIPO DE PERSONA resuelto a partir del número. Sin esta clave la función
                // defaultea a 'N' y TODA empresa nacería marcada como persona natural: al
                // reconsultarla el formulario ya no la reconocería.
                'dinardap' => [
                    'cli_tiposujeto' => ValidacionCedulaRucService::tipoSujetoPorIdentificacion(
                        $datos['identificacion'],
                        (int) $datos['tipoidentificacion']
                    ) ?: 'N',
                ],
            ]];
        });
    }

    // ESTADO C — HIDRATACIÓN. Se parte de la ficha COMPLETA que devuelve el buscador y solo se
    // pisan los campos del formulario. Es obligatorio: crm.fn_clientes_modificar reescribe el
    // cliente entero y solo ~14 columnas llevan COALESCE, así que mandar únicamente los campos
    // de esta pantalla borraría geo, cónyuge, actividad, datos bancarios y cupo. Además las PKs
    // que trae la foto (dir_id / tel_id / ctip_id / refane_id) son las que evitan que cada
    // guardado DUPLIQUE las filas hijas y cree una dirección principal nueva.
    private function modificarClienteDesdeFoto(Request $request, array $datos, array $foto, string $corredor, int $diasCorredor)
    {
        $vinculado = $this->corredorVinculado((int) $foto['cli_id'], $diasCorredor);
        if ($vinculado !== null && $vinculado !== $corredor) {
            return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
        }

        return $this->ejecutarGuardado($request, $corredor, $diasCorredor, 'Cliente actualizado con éxito', function () use ($datos, $foto) {
            // Clientes legacy sin dirección o teléfono principal: la función aborta con
            // CLIENTE_NO_ENCONTRADO. Se crean al vuelo, dentro de la transacción.
            $payload = $this->asegurarPrincipales($foto, $datos);

            // Los campos del formulario, y nada más. Todo lo demás viaja con el valor que ya
            // tenía en la base, así que se reescribe idéntico.
            $payload['ent_nombres'] = $datos['nombres'];
            $payload['ent_apellidos'] = $datos['apellidos'];
            $payload['ent_email'] = $datos['email'];
            $payload['ent_nombre_comercial'] = trim($datos['apellidos'] . ' ' . $datos['nombres']);
            $payload['direccion']['dir_calle_principal'] = $datos['direccion'];
            $payload['direccion']['dir_calle_secundaria'] = $datos['direccionSecundaria'];
            $payload['telefono']['tel_numero'] = $datos['telefono'];

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
    private function ejecutarGuardado(Request $request, string $corredor, int $diasCorredor, string $mensajeExito, callable $armarPayload)
    {
        DB::transaction(function () use ($request, $corredor, $diasCorredor, $armarPayload) {
            [$funcion, $payload] = $armarPayload();

            // Trazabilidad: aquí SÍ hay usuario JWT, así que el autor es el corredor interno.
            // 'usuario_auditoria' alimenta cliente.created_by/updated_by y el bloque 'auditoria'
            // va a la auditoría forense. Los DOS caminos lo mandan: si el update lo omitiera,
            // updated_by quedaría en NULL.
            $payload['usuario_auditoria'] = $corredor;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne("SELECT {$funcion}(?::jsonb) AS cli_id", [json_encode($payload)]);

            $this->vincularCorredor((int) $resultado->cli_id, $corredor, $diasCorredor);
        });

        return response()->json(RespuestaApi::returnResultado('success', $mensajeExito, null));
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

    // Lo ÚNICO que sale al navegador. La foto trae la ficha completa (geo, cónyuge, actividad,
    // cupo, referencias, datos bancarios); de aquí solo salen los campos que muestra el
    // formulario. El resto nunca cruza la red.
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
            // Throttle de la consulta a fuentes externas (parámetro CONSULTAR-IDENTIDAD),
            // resuelto por la misma función: evita golpear al SRI en cada apertura.
            'debe_consultar_identidad' => $foto['debe_consultar_identidad'] ?? true,
        ];
    }

    // Valores propios del canal, SOLO para el alta. cat_id y lpr_id NO se mandan: se dejan al
    // default de la función (cat_pordefecto / lpr_pordefecto), que es lo que ya venía haciendo
    // el camino preferido de este controlador.
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

        // Geo por defecto: MISMA regla que el modal del CRM (ClienteController::catalogos) —
        // provincia AZUAY + primer cantón ACTIVO alfabético + primera parroquia ACTIVA de ese
        // cantón. El filtro por *_activo NO es opcional: sin él el primer cantón alfabético de
        // AZUAY es ASUNCION, que está inactivo, y su única parroquia es "SIN PARROQUIAS".
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
            'prv_id' => $provincia->prv_id ?? null,
            'ctn_id' => $canton->ctn_id ?? null,
            'prq_id' => $parroquia->prq_id ?? null,
        ];
    }

    // Un cliente sin dirección o teléfono principal hace abortar a fn_clientes_modificar.
    // Se crean con los datos del formulario y se apuntan en la entidad; la función los
    // actualizará enseguida con esos mismos valores. (Este controlador ya lo hacía antes:
    // era la única cosa que hacía mejor que el formulario público.)
    private function asegurarPrincipales(array $foto, array $datos): array
    {
        $entId = (int) $foto['ent_id'];

        if (empty($foto['direccion']['dir_id'])) {
            $nuevaDireccion = new Direccion();
            $nuevaDireccion->dir_calle_principal = $datos['direccion'];
            $nuevaDireccion->dir_calle_secundaria = $datos['direccionSecundaria'];
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
            $nuevoTelefono->tel_numero = $datos['telefono'];
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

    // Corredor con vinculación VIGENTE, o null si está libre. Si la vinculación ya cumplió sus
    // días se desvincula y el cliente pasa a considerarse libre.
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

    // Crea la vinculación vigente cliente-corredor con tipo ALM quemado. Si el cliente ya
    // tiene una vigente no hace nada: el caso "pertenece a otro corredor" se rechaza antes
    // de llegar aquí, así que la que exista es de este mismo corredor.
    private function vincularCorredor(int $cliId, string $corredor, int $diasCorredor): void
    {
        $vigente = DB::selectOne("SELECT cli_id FROM public.clientes_multinivel
                                    WHERE cli_id = ?
                                        AND activo = true
                                        AND fecha_desvinculacion IS NULL
                                    LIMIT 1", [$cliId]);

        if ($vigente) {
            return;
        }

        $vinculo = new ClientesMultinivel();
        $vinculo->cli_id = $cliId;
        $vinculo->corredor = $corredor;
        $vinculo->tipo_corredor = self::TIPO_CORREDOR_ALM;
        $vinculo->dias_parametro = $diasCorredor;
        $vinculo->activo = true;
        $vinculo->save();
    }

    // Etiqueta del corredor interno: "usu_alias - APELLIDOS NOMBRES" del usuario
    // JWT (mismo formato que cliente.created_by). Se trunca a 100 caracteres.
    private function etiquetaCorredor(): ?string
    {
        $u = auth('api')->user();
        if (!$u) {
            return null;
        }

        $etiqueta = trim(trim($u->usu_alias) . ' - ' . trim($u->surname) . ' ' . trim($u->name));

        return mb_substr($etiqueta, 0, 100);
    }

    // Contexto del usuario para la auditoría forense de crm.fn_clientes_registrar
    // (mismo bloque que arma el ClienteController de openceo).
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }

    // Lee el parámetro CLICOR (días de vigencia de la vinculación cliente-corredor).
    // Acepta 0 o más días; error si no existe, está vacío o es negativo.
    private function obtenerDiasParametroCorredor()
    {
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'CLICOR')
            ->first();

        $valor = $parametro ? trim((string) $parametro->valor) : '';

        if ($valor === '' || (int) $valor < 0) {
            return response()->json(RespuestaApi::returnResultado('error', 'No está configurado el parámetro, comuniquese con el administrador.', null));
        }

        return (int) $valor;
    }

    // Detecta la violación del índice único parcial uq_clientes_multinivel_vigente
    // (SQLSTATE 23505): otro corredor vinculó al mismo cliente en paralelo.
    private function esCarreraVinculacion(Exception $e): bool
    {
        return $e instanceof QueryException
            && ($e->errorInfo[0] ?? null) === '23505'
            && strpos($e->getMessage(), 'uq_clientes_multinivel_vigente') !== false;
    }

    // Si la vinculación vigente del cliente ya cumplió los días de CLICOR la
    // desactiva (activo = false + fecha_desvinculacion = NOW()). Devuelve true
    // si se desvinculó (el cliente queda libre para un nuevo corredor).
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
}
