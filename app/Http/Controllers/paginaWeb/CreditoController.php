<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Controllers\email\EmailController;
use App\Http\Controllers\formulario\SolicitudesCreditosWebController;
use App\Http\Controllers\varios\ConsultaIdentidadExternoController;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Consultas\ConsultasService;
use App\Servicios\Consultas\ProveedorRespuestasService;
use App\Servicios\ValidacionCedulaRucService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Formulario institucional (/formulario), migrado de ecommerce-back.
// La lupa resuelve el cliente (ERP, o Ecuador Legal / SRI si no existe). Al enviar: alta al contado
// si no está en el ERP (uno existente NUNCA se modifica), solicitud y correo. Ya no crea caso.
class CreditoController extends Controller
{
    // Canal sin login: queda registrado con el usuario genérico USUARIO GENERAL ECOMMERCE 219
    private const USU_ID_FORMULARIO = 173;
    private const CANAL = 'FORMULARIO INSTITUCIONAL';

    // Constantes del alta al contado: las mismas del canal STS (DynamoClienteController)
    private const TTE_ID_CELULAR = 2;
    private const DIR_TIPO_DEFAULT = 'CASA';
    private const SFP_ID_DEFAULT = 1;
    private const EMP_ID_DEFAULT = 1;
    private const CLI_SEXO_DEFAULT = 'M';
    private const CLI_ESTADOCIVIL_DEFAULT = 'S';

    // Códigos que lanza crm.fn_clientes_registrar (vía crm.fn_cliente_validaciones), como en el STS
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
        'IDENTIFICACION_INVALIDA' => 'La identificación ingresada no es válida.',
        'IDENTIFICACION_DUPLICADA' => 'Ya existe un cliente con esa identificación.',
        'CODIGO_DUPLICADO' => 'Ya existe un cliente con ese código.',
        'POLITICA_INVALIDA' => 'La política seleccionada no es válida.',
    ];

    // Lupa: solo cédula o RUC. Si está en el ERP muestra su ficha; si no, consulta la fuente oficial
    // (queda en crm.consulta_identidad, de donde el envío toma el nombre).
    public function buscarCliente(Request $request)
    {
        try {
            $identificacion = trim((string) $request->input('identificacion'));
            $tipo = (int) $request->input('tipoidentificacion');

            $error = $this->validarIdentificacion($identificacion, $tipo);
            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, null));
            }

            // Ya existe (cliente, proveedor o garante): solo se muestra, nunca se toca
            $foto = $this->fotoCliente($identificacion, $tipo);
            if ($foto) {
                return response()->json(RespuestaApi::returnResultado('success', 'Cliente encontrado', [
                    'existe' => true,
                    'cliente' => $this->nombreCompleto($foto['ent_apellidos'] ?? '', $foto['ent_nombres'] ?? ''),
                    'direccion' => $foto['direccion']['dir_calle_principal'] ?? '',
                    'provincia' => $foto['direccion']['dir_prv_nombre'] ?? '',
                    'ciudad' => $foto['direccion']['dir_ctn_nombre'] ?? '',
                    'email' => $foto['ent_email'] ?? '',
                ]));
            }

            // No existe: cédula -> Ecuador Legal, RUC -> SRI (Ecuador Legal no acepta RUC)
            $consultas = app(ConsultaIdentidadExternoController::class);
            $respuesta = $tipo === 2
                ? $consultas->consultarRucSri($identificacion, self::USU_ID_FORMULARIO, ProveedorRespuestasService::CANAL_PAGINA_WEB)
                : $consultas->consultarCedulaEcuadorLegal($identificacion, self::USU_ID_FORMULARIO, ProveedorRespuestasService::CANAL_PAGINA_WEB);
            $data = $respuesta->getData(true);

            $oficial = ($data['status'] ?? '') === 'success'
                ? $this->nombresOficiales($data['data'] ?? [], $data['data']['nombres'] ?? null, $data['data']['apellidos'] ?? null)
                : null;

            if (!$oficial) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo validar la identidad, intente nuevamente.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Identidad validada', [
                'existe' => false,
                'cliente' => $this->nombreCompleto($oficial['apellidos'], $oficial['nombres']),
                'direccion' => '',
                'provincia' => '',
                'ciudad' => '',
                'email' => '',
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al buscar el cliente.', $e->getMessage()));
        }
    }

    // Antes crearCaso (ecommerce-back): alta al contado si no existe, solicitud y correo; ya no crea caso
    public function enviarCliente(Request $request)
    {
        try {
            $validation = Validator::make(
                $request->all(),
                [
                    'identificacion' => 'required',
                    'tipoidentificacion' => 'required|in:1,2',
                    'direccion' => 'required',
                    'prv_nombre' => 'required',
                    'ctn_nombre' => 'required',
                    'email' => 'required',
                    'telefono' => 'required',
                ],
                [
                    'identificacion.required' => 'La identificacion es requerido',
                    'tipoidentificacion.required' => 'El tipo identificacion es requerido',
                    'tipoidentificacion.in' => 'Solo se admite cédula o RUC.',
                    'direccion.required' => 'La direccion es requerido',
                    'prv_nombre.required' => 'La provincia es requerido',
                    'ctn_nombre.required' => 'La ciudad es requerido',
                    'email.required' => 'El email es requerido',
                    'telefono.required' => 'El telefono es requerido',
                ]
            );

            if ($validation->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validation->errors()->first(), null));
            }

            $identificacion = trim((string) $request->input('identificacion'));
            $tipo = (int) $request->input('tipoidentificacion');

            $error = $this->validarIdentificacion($identificacion, $tipo);
            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, null));
            }

            $mensajeError = null;

            DB::transaction(function () use ($request, $identificacion, $tipo, &$mensajeError) {
                $foto = $this->fotoCliente($identificacion, $tipo);

                if ($foto) {
                    // Existe en el ERP: no se modifica nada, solo se toma su nombre para la solicitud
                    $nombre = ['nombres' => $foto['ent_nombres'] ?? '', 'apellidos' => $foto['ent_apellidos'] ?? ''];
                } else {
                    // Nombre oficial que dejó la lupa; el navegador nunca lo manda
                    $nombre = $this->nombreOficialConsultado($identificacion);
                    if (!$nombre) {
                        $mensajeError = 'Busque la identificación con la lupa antes de enviar.';
                        return;
                    }

                    $this->registrarClienteContado($request, $identificacion, $tipo, $nombre);
                }

                // La solicitud guarda los nombres resueltos aquí, separados en sus columnas
                $request->merge([
                    'identificacion' => $identificacion,
                    'tipoidentificacion' => $tipo,
                    'nombres' => $nombre['nombres'],
                    'apellidos' => $nombre['apellidos'],
                ]);

                $respSolicitud = (new SolicitudesCreditosWebController())->addSolicitudCreditoWeb($request);
                $solicitud = $respSolicitud->getData();
                if (($solicitud->status ?? '') !== 'success') {
                    throw new Exception('No se pudo guardar la solicitud: ' . json_encode($solicitud->data ?? null));
                }

                $empresa = $request->has('empresa') ? $request->empresa : 'NINGUNA'; // veo si existe el campo empresa sino coloco NINGUNA

                // Todos los datos que vamos a enviar en el correo
                $object = (object) [
                    'asunto' => 'AUTORIZACIÓN PARA EL TRATAMIENTO DE DATOS PERSONALES Y DE RIESGO CREDITICIO ENTRE EL CLIENTE Y ALMESPANA CIA. LTDA',
                    // Link al crm-back que atiende; la empresa puede tener espacios
                    'linkAutorizar' => url('api/ecommerce/autorizarTratamientoDatos/' . rawurlencode($empresa) . '/' . $solicitud->data->id),
                ];

                $enviarCorreo = new EmailController();
                $enviarCorreo->sendEmailTerminosCondiciones($request->email, $object);
            });

            if ($mensajeError) {
                return response()->json(RespuestaApi::returnResultado('error', $mensajeError, null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Gracias por aplicar en breve un asesor se comunicara con Ud.', null));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo enviar el formulario, intente nuevamente.', $e->getMessage()));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo enviar el formulario, intente nuevamente.', $e->getMessage()));
        }
    }

    // Alta al contado con crm.fn_clientes_registrar, mismo payload que el STS (registrarClienteContado).
    // Dirección, celular y email salen del formulario; provincia/ciudad se casan con el catálogo.
    private function registrarClienteContado(Request $request, string $identificacion, int $tipo, array $nombre): void
    {
        $defaults = $this->defaultsContado();
        $geo = ConsultasService::geoPorNombre($request->input('prv_nombre'), $request->input('ctn_nombre'), null, $defaults);

        $payload = [
            'ent_identificacion' => $identificacion,
            'ent_tipo_identificacion' => $tipo,
            'ent_nombres' => $nombre['nombres'],
            'ent_apellidos' => $nombre['apellidos'],
            'ent_email' => mb_strtolower(trim((string) $request->input('email'))),
            'ent_nombre_comercial' => trim($nombre['apellidos'] . ' ' . $nombre['nombres']),
            'pol_id' => $defaults['pol_id'],
            'cat_id' => $defaults['cat_id'],
            'lpr_id' => $defaults['lpr_id'],
            'emp_id' => self::EMP_ID_DEFAULT,
            'cli_credito' => false,
            'tipos_pago' => [['sfp_id' => self::SFP_ID_DEFAULT, 'ctip_default' => true]],
            'direccion' => [
                'dir_calle_principal' => mb_strtoupper(trim((string) $request->input('direccion'))),
                'dir_calle_secundaria' => '',
                'dir_principal' => true,
                'dir_activo' => true,
                'dir_tipo' => self::DIR_TIPO_DEFAULT,
                'dir_prv_id' => $geo['prv_id'],
                'dir_ctn_id' => $geo['ctn_id'],
                'dir_prq_id' => $geo['prq_id'],
            ],
            'telefono' => [
                'tte_id' => self::TTE_ID_CELULAR,
                'tel_numero' => trim((string) $request->input('telefono')),
                'tel_principal' => true,
                'tel_activo' => true,
            ],
            'tit_id' => $defaults['tit_id'],
            'ent_fechanacimiento' => null,
            // Tipo de persona por la identificación (tercer dígito 9/6 = sociedad), nunca del request
            'dinardap' => [
                'cli_tiposujeto' => ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipo) ?: 'N',
            ],
        ];

        // Sexo M y estado civil S como pane_id (la función deriva cli_sexo/cli_estadocivil de ahí)
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

        $payload = array_merge($payload, $this->contextoAuditoria($request));

        DB::selectOne('SELECT crm.fn_clientes_registrar(?::jsonb) AS cli_id', [json_encode($payload)]);
    }

    // Mismos valores del alta del STS (defaultsCanal): CONTADO, título TIT, categoría 'clien', lista LPR,
    // sexo/estado civil y geo por defecto (AZUAY + primer cantón y parroquia activos).
    private function defaultsContado(): array
    {
        $politica = DB::selectOne("SELECT pol_id FROM politica WHERE pol_nombre = 'CONTADO' AND pol_tipocli = 1");
        if (!$politica) {
            throw new Exception('No está configurada la política CONTADO.');
        }

        $titulo = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id FROM parametro
                                    WHERE par_abreviacion='TIT' AND mod_abreviatura='CLI' LIMIT 1");

        $categoria = DB::selectOne("SELECT cat_id FROM catcliente WHERE cat_abreviacion = 'clien'");

        $listaPre = DB::selectOne("SELECT lpr_id FROM listapre
                                    WHERE lpr_nombre IN (SELECT par_texto FROM parametro
                                                          WHERE par_abreviacion='LPR' AND mod_abreviatura='CLI' LIMIT 1)
                                    LIMIT 1");

        // Grupos DINARDAP: 2 = SEXO, 3 = ESTADO CIVIL
        $sexo = DB::selectOne("SELECT pane_id FROM parametro_anexo
                                WHERE pane_grupo_codigo = 2 AND pane_cod_dinardap = ? LIMIT 1", [self::CLI_SEXO_DEFAULT]);
        $estadoCivil = DB::selectOne("SELECT pane_id FROM parametro_anexo
                                        WHERE pane_grupo_codigo = 3 AND pane_cod_dinardap = ? LIMIT 1", [self::CLI_ESTADOCIVIL_DEFAULT]);

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

    // Autor del alta en la auditoría (created_by y auditoría forense): este formulario + IP del visitante
    private function contextoAuditoria(Request $request): array
    {
        return [
            'usuario_auditoria' => self::CANAL,
            'auditoria' => [
                'usuario_id' => self::USU_ID_FORMULARIO,
                'usuario_login' => self::CANAL,
                'usuario_nombre' => self::CANAL,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => (string) Str::uuid(),
            ],
        ];
    }

    // Ficha del ERP con la misma función del CRUD del CRM (también trae al que existe sin ser cliente)
    private function fotoCliente(string $identificacion, int $tipo): ?array
    {
        $fila = DB::selectOne('SELECT datos FROM crm.fn_cliente_buscar_por_identificacion(?, ?)', [$identificacion, $tipo]);

        if (!$fila || empty($fila->datos)) {
            return null;
        }

        return json_decode($fila->datos, true);
    }

    // Última consulta oficial (Ecuador Legal / SRI) de esa identificación, la que deja la lupa
    private function nombreOficialConsultado(string $identificacion): ?array
    {
        $fila = DB::selectOne('SELECT nombres, apellidos, respuesta FROM crm.consulta_identidad
                                WHERE identificacion = ? ORDER BY id DESC LIMIT 1', [$identificacion]);

        if (!$fila) {
            return null;
        }

        return $this->nombresOficiales(json_decode((string) $fila->respuesta, true) ?: [], $fila->nombres, $fila->apellidos);
    }

    // Jurídica: razón social completa en apellidos y '.' en nombres, como el STS
    private function nombresOficiales(array $respuesta, ?string $nombres, ?string $apellidos): ?array
    {
        if (($respuesta['tipo_sujeto'] ?? 'N') === 'J') {
            $razonSocial = trim((string) ($respuesta['razonSocial'] ?? ''));
            if ($razonSocial === '') {
                $razonSocial = trim((string) $apellidos . ' ' . (string) $nombres);
            }

            return $razonSocial !== '' ? ['nombres' => '.', 'apellidos' => mb_strtoupper($razonSocial)] : null;
        }

        $apellidos = trim((string) $apellidos);
        if ($apellidos === '') {
            return null;
        }

        return ['nombres' => mb_strtoupper(trim((string) $nombres)), 'apellidos' => mb_strtoupper($apellidos)];
    }

    private function nombreCompleto(string $apellidos, string $nombres): string
    {
        return trim(trim($apellidos) . ' ' . (trim($nombres) === '.' ? '' : trim($nombres)));
    }

    private function validarIdentificacion(string $identificacion, int $tipo): ?string
    {
        if ($identificacion === '') {
            return 'Debe ingresar la identificación.';
        }

        if ($tipo === 1) {
            return ValidacionCedulaRucService::esCedulaValida($identificacion) ? null : 'La cédula ingresada no es válida.';
        }

        if ($tipo === 2) {
            return ValidacionCedulaRucService::esRucValido($identificacion) ? null : 'El RUC ingresado no es válido.';
        }

        return 'Solo se admite cédula o RUC.';
    }

}
