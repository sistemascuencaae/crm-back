<?php

namespace App\Http\Controllers\consultas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\varios\ConsultaIdentidadExternoController;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Consultas\ConsultasService;
use App\Servicios\Consultas\GarancheckService;
use App\Servicios\Consultas\ProveedorConsulta;
use App\Servicios\Consultas\ProveedorRespuestasService;
use App\Servicios\Consultas\YupanaService;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

// Consulta de clientes a proveedores externos (GaranCheck hoy). Flujo genérico:
// identificación → foto del cliente → caché → proveedor → alta / actualización mínima del
// cliente → crm.fn_cliente_consultas_registrar (historial + resumen + forense) → respuesta.
// El JSON del proveedor solo lo entiende su XService; aquí no se toca ninguna función de cliente.
// Ver crm/PLANES/GARANCHECK/produccion_garancheck.sql.
class ConsultasController extends Controller
{
    private const MSG_CLIENTE_INCOMPLETO_CREDITO = 'Este cliente tiene datos pendientes; complételos en el módulo de clientes del CRM.';

    // Mensaje amigable por código de excepción de las funciones PG. El orden importa: los códigos
    // largos van antes que los que son su subcadena (REQUIERE_TIPOEMPRESA antes que EMPRESA).
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
        // fn_cliente_consultas_registrar
        'CACHE_ORIGEN_NO_EXISTE' => 'La consulta guardada ya no existe; vuelva a consultar.',
        'CLIENTE_NO_EXISTE' => 'No se encontró el cliente para guardar la consulta.',
        'USUARIO_NO_EXISTE' => 'No se pudo identificar al usuario.',
        'JSON_INVALIDO' => 'El proveedor devolvió una respuesta inválida.',
        // 30-oct: fn_clientes_contacto_modificar (el modal del campo IDENTIFICACION CLIENTE PROVEEDOR) y el perfil de Yupana.
        'TELEFONO_INVALIDO' => 'El celular debe tener 10 dígitos y empezar con 09.',
        'EMAIL_INVALIDO' => 'El correo no es válido.',
        'REQUIERE_DIRECCION' => 'Debe ingresar la calle de la dirección.',
        'UBICACION_INVALIDA' => 'La parroquia no corresponde al cantón, o el cantón a la provincia.',
        'REQUIERE_NOMBRES' => 'Debe ingresar los nombres y apellidos del cliente.',
        'CASO_NO_EXISTE' => 'El caso no existe.',
        'CONSULTA_NO_EXISTE' => 'La consulta guardada ya no existe; vuelva a consultar.',
        'PERFIL_INVALIDO' => 'El proveedor devolvió un perfil inválido.',
        'PROVEEDOR_SIN_PERFIL' => 'Esa consulta no es de Yupana; no tiene perfil que completar.',
    ];

    // 27-oct: una consulta a la vez por identificación (ver tomarCandadoConsulta). Se espera un poco más que el
    // tiempo máximo del proveedor (GarancheckService::TIMEOUT_SEGUNDOS, 180 s).
    private const CANDADO_CONSULTA = 'crm.cliente_historial_consultas';
    private const ESPERA_CANDADO_SEGUNDOS = 200;
    private const MSG_CONSULTA_EN_CURSO = 'Ya hay una consulta de esta identificación en curso; espere un momento y vuelva a buscar.';

    // 27-oct: el proveedor respondió (y cobró) pero sin identidad. A una persona que no es cliente no se la puede
    // crear ni guardarle la consulta: se recuerda unos minutos para no volver a pagar con cada clic.
    // La consulta sin identidad de un cliente que ya existe sí se guarda, y como caché vale solo estos mismos minutos
    // (ver consultaSinIdentidadVencida): pasado ese lapso se vuelve a consultar.
    private const MINUTOS_SIN_IDENTIDAD = 30;
    private const MSG_SIN_IDENTIDAD_RECIENTE = 'El proveedor no devolvió los datos de identidad de esta identificación hace menos de 30 minutos; vuelva a intentar más tarde.';
    private const MSG_SIN_IDENTIDAD_EXISTENTE = 'El proveedor no devolvió los datos de identidad; los nombres quedan como estaban.';

    // 27-oct (decisión del usuario): lo que recibe el navegador en el canal del CRM (Nuevo Caso): lo del 15-sep (id de la
    // consulta, score y las calificaciones del resumen) más la precarga y el estado. Sin la evaluación completa del buró,
    // sin cli_id ni nombres.
    // 28-oct (pedido del usuario): también la evaluación, la fecha de la consulta original y el nombre del cliente, para el
    // modal de calificación de Nuevo Caso (el mismo del STS).
    private const CAMPOS_NUEVO_CASO = [
        'id', 'score', 'resultado_buro', 'calificacion_buro', 'resultado_general', 'calificacion_general', 'calificacion_cliente',
        'datos_cliente', 'es_cache', 'cliente_actualizado', 'motivo',
        'evaluacion', 'fecha_consulta', 'nombre_cliente',
        // 29-oct: qué proveedor se consultó, para los textos del modal del cliente (mañana puede ser Yupana).
        'proveedor', 'proveedor_nombre',
        // 29-oct: el código (id de crm.proveedor_respuestas) que ve el usuario cuando no llega la calificación.
        'codigo_respuesta',
    ];

    // 29-oct (pedido del usuario): lo que ve el usuario cuando falla el proveedor, sin el detalle técnico. El error real se
    // busca con el código (codigoRespuesta) en la pantalla de respuestas de proveedores.
    private const MSG_PROVEEDOR_EXTERNO = 'No se pudo obtener datos del proveedor externo.';

    // 29-oct: id de la fila de crm.proveedor_respuestas del último fallo del proveedor en procesarConsulta (el código que
    // ve el usuario). null si el fallo no fue del proveedor o la fila no se pudo guardar.
    private ?int $codigoRespuesta = null;

    // 30-oct: la agencia no tiene calificador (crm.agencia.consulta_proveedor vacío): con respaldo el cliente igual se
    // crea con Ecuador Legal / SRI y queda sin calificación; sin respaldo, este error.
    private const MSG_SIN_CALIFICADOR = 'La agencia no tiene configurado el calificador de consulta.';

    // 30-oct: el calificador decidido para la consulta en curso (null = la agencia no tiene) y la decisión completa de
    // crm.fn_cliente_consultas_proveedor_decidir (proveedor, codigo_tienda, agencia_codigo, agencia_nombre, origen, multinivel).
    private ?ProveedorConsulta $proveedorUsado = null;
    private array $decisionProveedor = [];
    // 30-oct (fase 3b): la agencia con la que se decide el calificador cuando consulta el botón Calificar del caso
    // (crm.caso.codigo_agencia); null = la del usuario que consulta (la lupa de Nuevo Caso, el STS).
    private ?string $agenciaForzada = null;

    // 27-oct: en un error del canal del CRM el navegador solo recibe un mensaje CONOCIDO: los de esta clase, los de
    // MENSAJES_ERROR y los de GarancheckService que no traen texto interno. Cualquier otro (el cURL con la URL del
    // proveedor, el archivo y la línea de una excepción de PHP...) se cambia por MSG_ERROR_CONSULTA.
    private const MSG_ERROR_CONSULTA = 'Error al consultar el cliente.';
    private const MENSAJES_CONOCIDOS = [
        'Debe ingresar una identificación.',
        'Identificación inválida.',
        'La identificación ingresada no es válida.',
        'No se pudo identificar al usuario.',
        'Proveedor no configurado, comuníquese con el administrador.',
        'El proveedor devolvió una respuesta vacía o inválida.',
        'No está configurada la política CONTADO, comuniquese con el administrador.',
    ];
    private const PATRONES_MENSAJES_CONOCIDOS = [
        '/^El proveedor respondió con error HTTP \d{3}$/u',
        '/^El proveedor no devolvió los datos de identidad \((civil|sri) sin datos\); no se pudo registrar al cliente\.$/u',
        '/^No está configurado el parámetro CONSULTA-[A-Z0-9_-]+, comuníquese con el administrador\.$/u',
        // El del catálogo de errores del proveedor ({codigo, mensaje}), solo si es texto llano.
        '/^Proveedor: [\p{L}\p{N} .,;()¿?¡!-]{1,200}$/u',
    ];

    // Version 1.0
    // POST /api/consultas/cliente — la consulta la hace un usuario del CRM (corredor NULL).
    // 27-oct: la usa el campo cliente de Nuevo Caso. Valida la entrada como el canal del corredor (una
    // identificación que llegaba como arreglo daba un error 500) y responde solo lo que usa esa pantalla.
    public function consultarCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identificacion' => 'required|string',
        ], [
            'identificacion.required' => 'Debe ingresar una identificación.',
            'identificacion.string' => 'Identificación inválida.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        // 30-oct: el calificador lo decide procesarConsulta (la agencia); aquí se toma el que usó. Si salió antes de decidir
        // (identificación inválida, consulta en curso), se decide por la agencia del usuario para nombrarlo igual.
        // 30-oct: respaldo = true (campo IDENTIFICACION CLIENTE PROVEEDOR): si el calificador falla, igual se crea al cliente con
        // Ecuador Legal / SRI y se responde sin calificación (nunca se frena al vendedor). El campo viejo no lo manda.
        $respuesta = $this->procesarConsulta($request, trim($request->input('identificacion')), null, null, $request->boolean('respaldo'), ProveedorRespuestasService::CANAL_NUEVO_CASO);

        return $this->respuestaNuevoCaso(
            $respuesta,
            $this->proveedorUsado ?? $this->proveedorConsulta(null, (int) auth('api')->id())
        );
    }

    // Version 1.0
    // 30-oct (fase 3b): la consulta del botón Calificar / Reintentar del caso (CasoConsultaGarancheckController::calificar).
    // Igual que la lupa de Nuevo Caso, pero el calificador se decide por la agencia DEL CASO (no la del analista que
    // aprieta el botón) y sin respaldo: el cliente ya existe, así que si el calificador falla solo se responde el
    // motivo con su Cód. Devuelve la respuesta completa de procesarConsulta (status, message, data.codigo_respuesta...).
    public function consultarParaCaso(Request $request, string $identificacion, ?string $agenciaCodigo)
    {
        $this->agenciaForzada = trim((string) $agenciaCodigo) !== '' ? trim((string) $agenciaCodigo) : null;

        try {
            return $this->procesarConsulta($request, $identificacion, null, null, false, ProveedorRespuestasService::CANAL_CASO);
        } finally {
            $this->agenciaForzada = null;
        }
    }

    // Version 1.0
    // GET /api/consultas/cliente/cache/{identificacion} — 28-oct: SIN consultar ni cobrar, dice si la lupa de Nuevo Caso
    // va a salir de la caché (data.cache = true) o va a ir a GaranCheck (false), con la MISMA regla que procesarConsulta
    // (idCacheConsulta). Solo decide el texto del spinner; la consulta vuelve a decidir al llegar.
    public function cacheCliente($identificacion)
    {
        try {
            $identificacion = trim((string) $identificacion);
            $tipoIdentificacion = $this->tipoIdentificacion($identificacion);
            if ($tipoIdentificacion === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La identificación ingresada no es válida.', null));
            }

            $foto = $this->fotoCliente($identificacion, $tipoIdentificacion);
            $cliId = !empty($foto['cli_id']) ? (int) $foto['cli_id'] : null;
            // 30-oct: el calificador lo decide la agencia (o MULTINIVEL si el cliente es del multinivel); puede no haber.
            $proveedor = $this->proveedorConsulta($cliId, (int) auth('api')->id());
            $idCache = $this->idCacheConsulta($cliId, $proveedor, $this->modoCache(), $identificacion, $tipoIdentificacion);

            // 29-oct: también el proveedor, para el texto del spinner ("Consultando GaranCheck...").
            return response()->json(RespuestaApi::returnResultado('success', 'Caché revisada.', [
                'cache' => $idCache !== null,
                'proveedor' => $proveedor ? $proveedor->nombre() : null,
                'proveedor_nombre' => $proveedor ? $proveedor->nombreVisible() : 'Sin calificador',
                'agencia' => $this->decisionProveedor['agencia_nombre'] ?? null,
            ]));
        } catch (Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', self::MSG_ERROR_CONSULTA, null));
        }
    }

    // Version 1.0
    // POST /sts/consultas/cliente — la consulta la hace un corredor multinivel (JWT del proveedor
    // STS). Además de guardar, vincula al cliente con el corredor en clientes_multinivel.
    public function consultarClienteCorredor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identificacion' => 'required|string',
            'corredor' => 'required|string',
        ], [
            'identificacion.required' => 'Debe ingresar una identificación.',
            'identificacion.string' => 'Identificación inválida.',
            'corredor.required' => 'Debe ingresar un corredor.',
            'corredor.string' => 'Corredor inválido.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        return $this->procesarConsulta($request, trim($request->input('identificacion')), trim($request->input('corredor')), null, false, ProveedorRespuestasService::CANAL_API_STS);
    }

    // Version 1.0
    // POST /api/consultas/cliente/contacto — 30-oct: el modal del campo "IDENTIFICACION CLIENTE PROVEEDOR" (Nuevo Caso) guarda el
    // contacto del cliente: email (opcional), celular (requerido) y la dirección con provincia/cantón/parroquia
    // (crm.fn_clientes_contacto_modificar: la dirección nueva queda como principal y la anterior pasa al histórico; NO toca
    // crm.fn_clientes_modificar). Si la persona todavía no es cliente (fallaron el calificador y el respaldo), vienen los
    // nombres y apellidos escritos por el vendedor y se crea primero (crm.fn_clientes_registrar, proveedor MANUAL).
    // Responde la ficha del cliente como la deja la base.
    // 01-nov (pedido del usuario: el modal con los mismos campos del formulario STS): la dirección es OPCIONAL. Sin ella solo se
    // guardan correo y celular y la dirección del cliente no se toca; el alta manual nace con "SN" en las dos calles.
    public function guardarContactoCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identificacion' => 'required|string',
            'telefono' => 'required|string',
            'email' => 'nullable|string|max:200',
            'nombres' => 'nullable|string|max:200',
            'apellidos' => 'nullable|string|max:200',
            // 'direccion' => 'required|array', // DESACTIVADO 01-nov
            // 'direccion.dir_calle_principal' => 'required|string|max:200', // DESACTIVADO 01-nov
            'direccion' => 'nullable|array',
            'direccion.dir_calle_principal' => 'required_with:direccion|string|max:200',
            'direccion.dir_calle_secundaria' => 'nullable|string|max:200',
            // 30-oct: el modal ya no muestra la ubicación; lo que falte lo completa crm.fn_direccion_ubicacion_completar.
            'direccion.dir_prv_id' => 'nullable|integer',
            'direccion.dir_ctn_id' => 'nullable|integer',
            'direccion.dir_prq_id' => 'nullable|integer',
        ], [
            'identificacion.required' => 'Debe ingresar una identificación.',
            'telefono.required' => 'Debe ingresar el celular.',
            // 'direccion.required' => 'Debe ingresar la dirección.', // DESACTIVADO 01-nov
            // 'direccion.dir_calle_principal.required' => 'Debe ingresar la calle de la dirección.', // DESACTIVADO 01-nov
            'direccion.dir_calle_principal.required_with' => 'Debe ingresar la calle de la dirección.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $identificacion = trim((string) $request->input('identificacion'));
        $tipoIdentificacion = $this->tipoIdentificacion($identificacion);
        if ($tipoIdentificacion === null) {
            return response()->json(RespuestaApi::returnResultado('error', 'La identificación ingresada no es válida.', null));
        }

        try {
            $contexto = ConsultasService::contextoAuditoria($request, null, null);
            $direccion = (array) $request->input('direccion');
            // 01-nov: el modal ya no manda la dirección (solo cédula, cliente, email y celular).
            $conDireccion = $direccion !== [];
            $telefono = preg_replace('/\D+/', '', (string) $request->input('telefono'));
            $email = trim((string) $request->input('email', ''));

            $foto = $this->fotoCliente($identificacion, $tipoIdentificacion);
            $cliId = !empty($foto['cli_id']) ? (int) $foto['cli_id'] : null;
            $creado = false;

            DB::transaction(function () use ($request, $identificacion, $tipoIdentificacion, $contexto, $direccion, $conDireccion, $telefono, $email, $foto, &$cliId, &$creado) {
                if ($cliId === null) {
                    // Alta manual: fallaron el calificador y la fuente oficial; el vendedor escribió los nombres.
                    $nombres = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $request->input('nombres'))), 'UTF-8');
                    $apellidos = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $request->input('apellidos'))), 'UTF-8');
                    if ($nombres === '' || $apellidos === '') {
                        throw new RuntimeException('REQUIERE_NOMBRES');
                    }

                    // 30-oct: la ubicación se completa por detrás con la misma regla que crm.fn_clientes_contacto_modificar
                    // (sin dirección actual: lo que mandó el front y el default AZUAY), para que el alta y el contacto
                    // coincidan y el cliente nuevo no nazca con una dirección en el histórico.
                    $ubicacion = $this->ubicacionCompletar($direccion);
                    $geo = $this->nombresGeo($ubicacion['prv_id'], $ubicacion['ctn_id'], $ubicacion['prq_id']);
                    $datos = [
                        'tipo_sujeto' => ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipoIdentificacion) ?: 'N',
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'nombre_comercial' => trim($apellidos . ' ' . $nombres),
                        'email' => $email !== '' ? mb_strtolower($email, 'UTF-8') : null,
                        'telefono' => $telefono !== '' ? $telefono : 'SN',
                        'direccion' => mb_strtoupper(trim((string) ($direccion['dir_calle_principal'] ?? '')), 'UTF-8') ?: 'SN',
                        'provincia' => $geo['provincia'],
                        'canton' => $geo['canton'],
                        'parroquia' => $geo['parroquia'],
                        'identificacion' => $identificacion,
                        'tipo_identificacion' => $tipoIdentificacion,
                        // Sin calificador ni fuente oficial: lo escribió el vendedor.
                        'proveedor' => 'MANUAL',
                    ];
                    // 01-nov: sin calle, "SN" en las dos calles (la dirección del caso cuando no viene dirección).
                    $datos = ConsultasService::direccionDelCanal($datos, false);
                    $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto);
                    $creado = true;
                }

                $contacto = [
                    'identificacion' => $identificacion,
                    'tipo_identificacion' => $tipoIdentificacion,
                    'email' => $email,
                    'telefono' => $telefono,
                    'usuario_auditoria' => $contexto['usuario_auditoria'],
                    'auditoria' => $contexto['auditoria'],
                ];
                // 01-nov: solo si vino; sin ella la función no toca la dirección del cliente.
                if ($conDireccion) {
                    $contacto['direccion'] = [
                        'dir_calle_principal' => $direccion['dir_calle_principal'] ?? null,
                        'dir_calle_secundaria' => $direccion['dir_calle_secundaria'] ?? null,
                        'dir_prv_id' => $direccion['dir_prv_id'] ?? null,
                        'dir_ctn_id' => $direccion['dir_ctn_id'] ?? null,
                        'dir_prq_id' => $direccion['dir_prq_id'] ?? null,
                    ];
                }

                DB::selectOne('SELECT crm.fn_clientes_contacto_modificar(?::jsonb) AS resultado', [json_encode($contacto, JSON_UNESCAPED_UNICODE)]);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Datos del cliente guardados con éxito', [
                'cli_id' => $cliId,
                'cliente_creado' => $creado,
                'cliente' => $this->fotoCliente($identificacion, $tipoIdentificacion),
            ]));
        } catch (Throwable $e) {
            $mensaje = strpos($e->getMessage(), 'CLIENTE_NO_EXISTE') !== false
                ? 'El cliente no existe; consulte la identificación otra vez.'
                : $this->mensajeError($e, 'No se pudieron guardar los datos del cliente.');

            return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
        }
    }

    // Mismo flujo para los tres canales; $corredor decide si hay vinculación multinivel.
    // $usuId: canal SIN JWT (el formulario público del corredor, que lo saca del token cifrado del
    // enlace). Pública para que DynamoClienteController la llame desde la lupa del formulario.
    // $conRespaldo: si GaranCheck no atiende, se crea igual al cliente con la fuente de identidad
    // oficial (Ecuador Legal / SRI), sin evaluación. Solo lo usa el formulario del corredor, para
    // que una caída del buró no le impida casar clientes; el canal del CRM sigue fallando duro.
    // 29-oct: $canal va a crm.proveedor_respuestas (NUEVO_CASO, FORMULARIO_STS, API_STS); sin él se deduce del corredor.
    public function procesarConsulta(Request $request, string $identificacion, ?string $corredor, ?int $usuId = null, bool $conRespaldo = false, ?string $canal = null)
    {
        // 27-oct: candado por identificación; se suelta en el finally.
        $conCandado = false;
        $canal = $canal ?? ($corredor === null ? ProveedorRespuestasService::CANAL_NUEVO_CASO : ProveedorRespuestasService::CANAL_API_STS);
        // 29-oct: la fila de crm.proveedor_respuestas de la llamada al proveedor, y su JSON mientras la consulta no esté
        // guardada: si el alta falla después de pagar, el JSON se deja en la fila (ver el catch).
        $idRespuesta = null;
        $jsonSinGuardar = null;
        $this->codigoRespuesta = null;

        try {
            // 1. Identificación: 10 dígitos = cédula, 13 = RUC; cualquier otra cosa se rechaza.
            $tipoIdentificacion = $this->tipoIdentificacion($identificacion);
            if ($tipoIdentificacion === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La identificación ingresada no es válida.', null));
            }

            // 2. Dueño de la consulta (usu_id) y autor de la auditoría. Del JWT, o del token del
            // enlace cuando la llama el formulario público.
            $usuarioId = auth('api')->id() ?? $usuId;
            if (!$usuarioId) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario.', null));
            }

            // 2b. 27-oct: una consulta a la vez por identificación (dos pestañas, dos vendedores, o un reintento
            // mientras el proveedor todavía responde). La segunda espera a que la primera guarde y sale de la caché
            // en vez de pagar otra. Va antes de la foto: si la primera creó al cliente, la segunda ya lo encuentra.
            $conCandado = $this->tomarCandadoConsulta($identificacion);
            if (!$conCandado) {
                return response()->json(RespuestaApi::returnResultado('error', self::MSG_CONSULTA_EN_CURSO, null));
            }

            // 3. Foto del cliente, de la MISMA función que usa el CRUD del CRM. Encuentra también a
            // una persona que existe pero aún no es cliente (cli_id NULL): esa va por el alta.
            $foto = $this->fotoCliente($identificacion, $tipoIdentificacion);
            $cliId = !empty($foto['cli_id']) ? (int) $foto['cli_id'] : null;

            // 3b. Corredor: si el cliente pertenece a OTRO corredor vigente se rechaza AQUÍ, antes de
            // la caché y del proveedor, para no gastar una consulta en un cliente ajeno.
            $diasCorredor = null;
            if ($corredor !== null) {
                $diasCorredor = ConsultasService::diasParametroCorredor();
                if ($cliId !== null) {
                    $vinculado = ConsultasService::corredorVinculado($cliId, $diasCorredor);
                    if ($vinculado !== null && $vinculado !== $corredor) {
                        return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
                    }
                }
            }

            // 30-oct: el calificador lo decide la agencia (crm.fn_cliente_consultas_proveedor_decidir): el cliente del
            // multinivel va con el de MULTINIVEL; si no, con el de la agencia del usuario. null = la agencia no tiene.
            $proveedor = $this->proveedorConsulta($cliId, (int) $usuarioId);
            $contexto = ConsultasService::contextoAuditoria($request, $corredor, $usuId);
            $base = [
                'usu_id' => $usuarioId,
                'proveedor' => $proveedor ? $proveedor->nombre() : null,
                'corredor' => $corredor,
                'identificacion' => $identificacion,
                'auditoria' => $contexto['auditoria'],
            ];

            // 27-oct: solo el canal del CRM (campo cliente de Nuevo Caso) recibe datos_cliente para
            // precargar el modal del cliente. Al corredor (STS) no se le mandan datos personales que antes
            // no recibía.
            $conPrecarga = $corredor === null;

            // 4. Caché por cliente (parámetro CONSULTA-CACHE, días; 0 = siempre en vivo). Con una
            // consulta vigente se copia desde el historial: no se va al proveedor ni se toca al cliente.
            $cache = $this->modoCache();

            if ($cliId !== null) {
                // 28-oct: la regla vive en idCacheConsulta; la usa también cacheCliente (el texto del spinner de Nuevo Caso).
                $idCache = $this->idCacheConsulta($cliId, $proveedor, $cache, $identificacion, $tipoIdentificacion);
                if ($idCache !== null) {
                    $resultado = DB::transaction(function () use ($base, $cliId, $idCache, $corredor, $diasCorredor) {
                        $registro = $this->registrarConsulta($base + ['cli_id' => $cliId, 'es_cache' => true, 'id_cache' => $idCache, 'valor' => null]);
                        if ($corredor !== null) {
                            ConsultasService::vincularCorredor($cliId, $corredor, ConsultasService::TIPO_CORREDOR_STS, $diasCorredor);
                        }

                        return $registro;
                    });

                    // 30-oct: la consulta del mes puede ser de OTRO calificador (se reusa entre calificadores): el JSON lo
                    // entiende el servicio del original, y 'proveedor' (ya en $resultado, lo da la función) es el del original.
                    $origen = $this->servicioDeConsulta($idCache, $proveedor);

                    // 30-oct: la consulta del mes de Yupana pudo quedar SIN perfil (perfilCliente falló): se vuelve a pedir solo
                    // el perfil y, si llega, se completa la consulta guardada, su resumen y los casos casados
                    // (crm.fn_cliente_consultas_perfil_actualizar). Si vuelve a fallar, la consulta se reusa igual, sin
                    // calificación, y el código es el de la fila PERFIL (para el aviso con Reintentar).
                    $codigoPerfil = $origen instanceof YupanaService
                        ? $this->completarPerfilYupana($origen, $idCache, $identificacion, $tipoIdentificacion, $canal, (int) $usuarioId, $cliId, $corredor, $contexto)
                        : null;

                    $resultado += [
                        'es_cache' => true,
                        'cliente_actualizado' => false,
                        'motivo' => null,
                        'cache' => $cache,
                        'proveedor' => $origen->nombre(),
                        'proveedor_nombre' => $origen->nombreVisible(),
                        // 29-oct: no se llamó al proveedor: no hay fila en crm.proveedor_respuestas (salvo el perfil que se reintentó).
                        'codigo_respuesta' => $codigoPerfil,
                    ];

                    // 27-oct: con la caché no hay JSON nuevo; la precarga sale de la consulta guardada que se reusa.
                    if ($conPrecarga) {
                        $jsonCache = $this->jsonConsultaGuardada($idCache);
                        $resultado['datos_cliente'] = $this->datosPrecarga($origen, $jsonCache, $identificacion, $tipoIdentificacion,
                            ConsultasService::clienteSinGuardar($cliId, $origen->nombre()));

                        // 28-oct: el modal de calificación de Nuevo Caso: la evaluación de la consulta guardada (sin ir al
                        // proveedor), con su fecha y el nombre del cliente. Si una política crítica tumbó la Evaluación de
                        // Fuentes, el resultado general dice lo mismo que en vivo.
                        $resultado['evaluacion'] = $this->evaluacionGuardada($origen, $jsonCache);
                        $resultado += $this->fechaYNombreConsulta($idCache, $cliId);
                        $criticas = $resultado['evaluacion']['evaluacionGeneral']['rechazoAlmespana'] ?? [];
                        if ($criticas) {
                            $resultado['resultado_general_proveedor'] = $resultado['resultado_general'] ?? null;
                            $resultado['resultado_general'] = false;
                            $resultado['politicas_criticas'] = $criticas;
                        }
                    }
                    // 01-nov: el corredor (Reintentar del aviso "Cliente sin calificación" del formulario STS) también recibe la
                    // calificación de la consulta del mes que se reusa, como la recibe en vivo (sin datos_cliente).
                    if (!$conPrecarga) {
                        $resultado['evaluacion'] = $this->evaluacionGuardada($origen, $this->jsonConsultaGuardada($idCache));
                    }

                    return response()->json(RespuestaApi::returnResultado('success', 'Consulta obtenida del historial', $resultado));
                }
            }

            // 5 y 6. Proveedor + datos mínimos del cliente. El tipo de persona lo decide el número,
            // no el JSON. Cualquier fallo (sin credenciales, red, HTTP, error del catálogo, o un 200
            // sin datos de identidad) sube tal cual y NO se guarda nada... salvo con $conRespaldo,
            // donde en vez de rendirse se va a la fuente oficial.
            // 27-oct: un 200 sin identidad de un cliente EXISTENTE ya no es un fallo: la consulta se guarda (está
            // pagada y la caché cubre los reintentos) y solo se saltan los nombres. De una persona que no es
            // cliente se recuerda MINUTOS_SIN_IDENTIDAD minutos y en ese lapso no se vuelve a llamar al proveedor.
            $json = null;
            $datos = null;
            $falloProveedor = null;
            $motivoSinIdentidad = null;

            // 30-oct: la agencia no tiene calificador y no hay consulta del mes que reusar: con respaldo se crea al cliente
            // con Ecuador Legal / SRI y se avisa (el caso queda sin calificación); sin respaldo, el error claro.
            if ($proveedor === null) {
                if (!$conRespaldo) {
                    return response()->json(RespuestaApi::returnResultado('error', self::MSG_SIN_CALIFICADOR, null));
                }

                return $this->altaPorIdentidadOficial(
                    $identificacion,
                    $tipoIdentificacion,
                    $foto,
                    $corredor,
                    $diasCorredor,
                    $contexto,
                    $usuarioId,
                    new RuntimeException(self::MSG_SIN_CALIFICADOR),
                    $canal,
                    self::MSG_SIN_CALIFICADOR
                );
            }

            try {
                if ($cliId === null) {
                    $sinIdentidadReciente = Cache::get($this->claveSinIdentidad($proveedor->nombre(), $identificacion));
                    if ($sinIdentidadReciente !== null) {
                        // 29-oct: sin llamar de nuevo; el código es el de la consulta que respondió sin identidad.
                        $this->codigoRespuesta = is_numeric($sinIdentidadReciente) && (int) $sinIdentidadReciente > 0 ? (int) $sinIdentidadReciente : null;
                        throw new RuntimeException(self::MSG_SIN_IDENTIDAD_RECIENTE);
                    }
                }

                $json = $proveedor->consultar($identificacion, [
                    'canal' => $canal,
                    'usu_id' => $usuarioId,
                    'cli_id' => $cliId,
                    'corredor' => $corredor,
                    // 30-oct: con qué agencia se consultó y qué identificación era (crm.proveedor_respuestas).
                    'agencia_codigo' => $this->decisionProveedor['agencia_codigo'] ?? null,
                    'tipo_identificacion' => $tipoIdentificacion === 1 ? 'CEDULA' : 'RUC',
                ]);
                $idRespuesta = $proveedor->idUltimaRespuesta();
                $jsonSinGuardar = $json;

                $tipoSujeto = ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipoIdentificacion) ?: 'N';
                try {
                    $datos = $proveedor->extraerDatosCliente($json, $tipoSujeto) + [
                        'identificacion' => $identificacion,
                        'tipo_identificacion' => $tipoIdentificacion,
                        'proveedor' => $proveedor->nombre(),
                    ];
                } catch (Throwable $e) {
                    // 29-oct: respondió (y cobró) sin identidad: la fila pasa a SIN_IDENTIDAD con la respuesta cruda.
                    ProveedorRespuestasService::modificar($idRespuesta, [
                        'resultado' => ProveedorRespuestasService::SIN_IDENTIDAD,
                        'mensaje' => $e->getMessage(),
                        'respuesta' => $json,
                    ]);
                    $jsonSinGuardar = null;
                    if ($cliId === null) {
                        // 29-oct: se recuerda el id de su fila (el código para el usuario); 0 si no se pudo guardar.
                        Cache::put($this->claveSinIdentidad($proveedor->nombre(), $identificacion), $idRespuesta ?? 0, now()->addMinutes(self::MINUTOS_SIN_IDENTIDAD));
                        throw $e;
                    }
                    $motivoSinIdentidad = self::MSG_SIN_IDENTIDAD_EXISTENTE;
                }
            } catch (Throwable $e) {
                // 29-oct: la fila que dejó el proveedor al fallar (consultar() la registra antes de lanzar).
                $this->codigoRespuesta = $proveedor->idUltimaRespuesta() ?? $this->codigoRespuesta;
                if (!$conRespaldo) {
                    throw $e;
                }
                $falloProveedor = $e;
            }

            // PLAN B: el buró no atendió. Se crea al cliente con Ecuador Legal / SRI y sin evaluación.
            if ($falloProveedor !== null) {
                return $this->altaPorIdentidadOficial(
                    $identificacion,
                    $tipoIdentificacion,
                    $foto,
                    $corredor,
                    $diasCorredor,
                    $contexto,
                    $usuarioId,
                    $falloProveedor,
                    $canal
                );
            }

            // 7. Todo o nada: cliente + consulta + vinculación en la misma transacción. La actualización
            // del cliente existente es best-effort dentro de un SAVEPOINT (transacción anidada).
            // 27-oct: $clienteNuevo dice a la precarga del modal si puede ponerle todo lo del proveedor (cliente recién
            // creado, o creado por una consulta y nunca guardado) o solo lo que el cliente tiene vacío.
            $clienteNuevo = false;
            $resultado = DB::transaction(function () use ($base, $foto, $cliId, $datos, $json, $contexto, $corredor, $diasCorredor, $proveedor, $motivoSinIdentidad, &$clienteNuevo) {
                $fichaActualizada = false;
                $motivo = null;

                if ($cliId === null) {
                    // 27-oct: en el canal del CRM la entidad que ya existía conserva su dirección, teléfono y nombres.
                    // $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto, $corredor === null); // DESACTIVADO 31-oct
                    // 31-oct (regla del usuario: "la creación de mi cliente es una cosa y las consultas de los proveedores son
                    // otras"): la persona que ya existía conserva lo suyo en TODOS los canales (antes el del corredor le ponía
                    // la dirección y el teléfono del proveedor), también sus nombres aunque el proveedor traiga otros. Una
                    // persona nueva se crea con los datos del proveedor. La consulta de identidad se sella solo en el canal
                    // del CRM y solo si el nombre es el mismo (o la persona no tenía nombres y quedan los del proveedor).
                    $propios = ConsultasService::tieneNombresPropios($foto, $datos);
                    $sellar = $corredor === null
                        && (!$propios || ConsultasService::nombresIguales($foto['ent_apellidos'] ?? null, $foto['ent_nombres'] ?? null, $datos));
                    // $cliId = ConsultasService::registrarCliente(ConsultasService::conNombresDeLaEntidad($datos, $foto), $foto, $contexto, $sellar, true); // DESACTIVADO 01-nov
                    // 01-nov: sin dirección del proveedor, la del canal ("SN - MULTINIVEL" en el formulario STS, "SN" en el caso).
                    $cliId = ConsultasService::registrarCliente(
                        ConsultasService::direccionDelCanal(ConsultasService::conNombresDeLaEntidad($datos, $foto), $corredor !== null),
                        $foto, $contexto, $sellar, true
                    );
                    $fichaActualizada = true;
                    $clienteNuevo = true;
                } elseif ($datos === null) {
                    // 27-oct: 200 sin identidad: la consulta se guarda igual y los nombres no se tocan.
                    $motivo = $motivoSinIdentidad;
                } else {
                    $clienteNuevo = ConsultasService::clienteSinGuardar($cliId, $proveedor->nombre());
                    // DESACTIVADO 31-oct (regla del usuario: la consulta ya no cambia los datos de un cliente que existe):
                    // try {
                    //     // 27-oct: SOLO nombres, apellidos y nombre comercial, y solo si cambian (antes la ficha entera
                    //     // pasaba por fn_clientes_modificar). En la respuesta van los nombres que quedaron en la base.
                    //     // En el canal del CRM también sella la consulta de identidad.
                    //     $nombres = DB::transaction(function () use ($cliId, $datos, $contexto, $corredor) {
                    //         return ConsultasService::actualizarNombresCliente($cliId, $datos, $contexto, $corredor === null);
                    //     });
                    //     $fichaActualizada = (bool) ($nombres['cliente_actualizado'] ?? false);
                    //     $motivo = $nombres['motivo'] ?? null;
                    //     $datos['apellidos'] = $nombres['ent_apellidos'] ?? $datos['apellidos'];
                    //     $datos['nombres'] = $nombres['ent_nombres'] ?? $datos['nombres'];
                    //     $datos['nombre_comercial'] = $nombres['ent_nombre_comercial'] ?? $datos['nombre_comercial'];
                    // } catch (Throwable $e) {
                    //     // La consulta se guarda igual.
                    //     $motivo = $this->mensajeError($e, 'No se pudo actualizar la ficha del cliente.');
                    // }
                    // 31-oct: el cliente que existe conserva su nombre aunque el proveedor traiga otro. Solo si el proveedor
                    // trae el MISMO nombre, en el canal del CRM, pasa por fn_cliente_nombres_modificar: no cambia nada y sella
                    // la consulta de identidad (antes también corregía los nombres cuando sabía dónde terminan los apellidos).
                    try {
                        $actuales = ConsultasService::nombresCliente($cliId);
                        if ($corredor === null && ConsultasService::nombresIguales($actuales['ent_apellidos'] ?? null, $actuales['ent_nombres'] ?? null, $datos)) {
                            DB::transaction(function () use ($cliId, $datos, $contexto) {
                                return ConsultasService::actualizarNombresCliente($cliId, $datos, $contexto, true);
                            });
                        }
                    } catch (Throwable $e) {
                        // La consulta se guarda igual.
                        $motivo = $this->mensajeError($e, 'No se pudo sellar la consulta de identidad del cliente.');
                    }
                }

                // 31-oct: en la respuesta, los nombres que quedaron en la base (lo nuestro), no los del proveedor: el STS los
                // muestra y los vuelve a mandar al Guardar, y la API del corredor los devuelve.
                if ($datos !== null) {
                    $nuestros = ConsultasService::nombresCliente($cliId);
                    $datos['apellidos'] = $nuestros['ent_apellidos'] ?? $datos['apellidos'];
                    $datos['nombres'] = $nuestros['ent_nombres'] ?? $datos['nombres'];
                    $datos['nombre_comercial'] = $nuestros['ent_nombre_comercial'] ?? $datos['nombre_comercial'];
                }

                $registro = $this->registrarConsulta($base + ['cli_id' => $cliId, 'es_cache' => false, 'id_cache' => null, 'valor' => $json]);

                if ($corredor !== null) {
                    ConsultasService::vincularCorredor($cliId, $corredor, ConsultasService::TIPO_CORREDOR_STS, $diasCorredor);
                }

                return $registro + [
                    'es_cache' => false,
                    'cliente_actualizado' => $fichaActualizada,
                    'motivo' => $motivo,
                    // Para el formulario del corredor: qué cliente quedó, con qué nombre y de qué
                    // fuente salió (aquí siempre el buró; con plan B lo responde altaPorIdentidadOficial).
                    'cli_id' => $cliId,
                    'proveedor' => $datos['proveedor'] ?? $proveedor->nombre(),
                    'proveedor_nombre' => $proveedor->nombreVisible(),
                    'nombres' => $datos['nombres'] ?? null,
                    'apellidos' => $datos['apellidos'] ?? null,
                    'nombre_comercial' => $datos['nombre_comercial'] ?? null,
                    'tipo_sujeto' => $datos['tipo_sujeto'] ?? 'N',
                    // Las dos evaluaciones completas (cabecera + detalle de políticas) para la
                    // pantalla de resultado, con la fecha de corte y las instituciones que el
                    // servicio deriva de la subtabla de evolución del buró.
                    'evaluacion' => $proveedor->evaluacionParaPantalla($json),
                ];
            });

            $resultado['cache'] = $cache;

            // 29-oct: la consulta quedó guardada: la fila de la respuesta se enlaza con ella y con el cliente.
            ProveedorRespuestasService::modificar($idRespuesta, [
                'consulta_id' => $resultado['id'] ?? null,
                'cli_id' => $resultado['cli_id'] ?? null,
            ]);
            $jsonSinGuardar = null;
            // 29-oct: si llega sin calificación, el modal del cliente muestra este código.
            $resultado['codigo_respuesta'] = $idRespuesta;

            // 27-oct: datos para precargar el modal del cliente (canal del CRM). Va fuera de la transacción:
            // si no salen, la consulta ya guardada no se pierde y el front abre el modal sin precarga.
            if ($conPrecarga) {
                $resultado['datos_cliente'] = $this->datosPrecarga($proveedor, $json, $identificacion, $tipoIdentificacion, $clienteNuevo);
                // 28-oct: para el modal de calificación de Nuevo Caso (la evaluación ya viene en $resultado).
                $resultado += $this->fechaYNombreConsulta((int) ($resultado['id'] ?? 0), $resultado['cli_id'] ?? null);
            }

            // Políticas críticas: si tumbaron la Evaluación de Fuentes, el resumen que se
            // devuelve tiene que decir lo mismo que la pantalla. Lo guardado por la función
            // PG conserva el veredicto del proveedor (ver 'resultado_general_proveedor').
            $criticas = $resultado['evaluacion']['evaluacionGeneral']['rechazoAlmespana'] ?? [];
            if ($criticas) {
                $resultado['resultado_general_proveedor'] = $resultado['resultado_general'] ?? null;
                $resultado['resultado_general'] = false;
                $resultado['politicas_criticas'] = $criticas;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Consulta realizada con éxito', $resultado));
        } catch (Throwable $e) {
            // 29-oct: el proveedor respondió bien (y cobró) pero el alta o el guardado fallaron: la respuesta completa se
            // queda en crm.proveedor_respuestas para no perderla.
            if ($jsonSinGuardar !== null) {
                ProveedorRespuestasService::modificar($idRespuesta, [
                    'mensaje' => 'Respondió pero la consulta no se guardó: ' . $e->getMessage(),
                    'respuesta' => $jsonSinGuardar,
                ]);
                $this->codigoRespuesta = $idRespuesta;
            }
            // Nada guardado (las transacciones ya revirtieron). El mensaje crudo va en data, como en el corredor.
            // 29-oct (pedido del usuario): en el formulario STS, con código, el texto genérico (la API del corredor sigue igual).
            $mensaje = $canal === ProveedorRespuestasService::CANAL_FORMULARIO_STS && $this->codigoRespuesta !== null
                ? $this->mensajeProveedorExterno()
                : $this->mensajeError($e, 'Error al consultar el cliente.');

            return response()->json(RespuestaApi::returnResultado('error', $mensaje, $e->getMessage()));
        } finally {
            if ($conCandado) {
                $this->soltarCandadoConsulta($identificacion);
            }
        }
    }

    // 27-oct: lo que ve el navegador en el canal del CRM: CAMPOS_NUEVO_CASO (la evaluación del buró, nombres y cli_id
    // se quedan en el servidor). 28-oct: la evaluación ya sale, para el modal de calificación; cli_id y el JSON no. En un error tampoco va el mensaje crudo de la excepción (puede traer el SQL con el
    // JSON de la consulta), y el mensaje solo si es uno conocido (mensajeParaNavegador).
    // 29-oct: en un error, data trae el proveedor y el motivo corto para el aviso del modal del cliente ("No se pudo
    // obtener la calificación del cliente." + Reintentar).
    private function respuestaNuevoCaso(JsonResponse $respuesta, ?ProveedorConsulta $proveedor): JsonResponse
    {
        $cuerpo = $respuesta->getData(true);
        $data = $cuerpo['data'] ?? null;
        $exito = ($cuerpo['status'] ?? '') === 'success';

        $cuerpo['data'] = $exito && is_array($data)
            ? array_intersect_key($data, array_flip(self::CAMPOS_NUEVO_CASO))
            : null;

        if (!$exito) {
            $crudo = (string) ($cuerpo['message'] ?? '');
            // 29-oct (pedido del usuario): message y motivo con el mismo texto, sin el detalle técnico del proveedor.
            $cuerpo['message'] = $this->motivoFalla($crudo, $this->mensajeParaNavegador($crudo));
            $cuerpo['data'] = [
                // 30-oct: null / "Sin calificador" cuando la agencia no tiene calificador.
                'proveedor' => $proveedor ? $proveedor->nombre() : null,
                'proveedor_nombre' => $proveedor ? $proveedor->nombreVisible() : 'Sin calificador',
                'motivo' => $cuerpo['message'],
                'codigo_respuesta' => $this->codigoRespuesta,
            ];
        }

        return response()->json($cuerpo, $respuesta->getStatusCode());
    }

    // 29-oct: el motivo corto del aviso. Pedido del usuario: el detalle técnico ("GaranCheck respondió con error HTTP 504.")
    // no le sirve al usuario: cualquier falla del proveedor (o con fila en crm.proveedor_respuestas) sale como
    // MSG_PROVEEDOR_EXTERNO y el código va aparte (codigo_respuesta). Lo que no es del proveedor (consulta en curso, cliente
    // duplicado...) va con el mensaje ya limpio de mensajeParaNavegador: nunca el texto interno de la excepción.
    private function motivoFalla(string $crudo, string $limpio): string
    {
        $delProveedor = $this->codigoRespuesta !== null
            || strpos($crudo, 'El proveedor no respondió') === 0
            || preg_match('/^El proveedor respondió con error HTTP \d{3}$/u', $crudo) === 1
            || $crudo === 'Proveedor no configurado, comuníquese con el administrador.'
            || strpos($crudo, 'No está configurado el parámetro CONSULTA-') === 0
            || $crudo === 'El proveedor devolvió una respuesta vacía o inválida.'
            || strpos($crudo, 'El proveedor no devolvió los datos de identidad (') === 0
            || $limpio === self::MSG_SIN_IDENTIDAD_RECIENTE
            || preg_match('/^Proveedor: /u', $limpio) === 1;

        return $delProveedor ? self::MSG_PROVEEDOR_EXTERNO : $limpio;
    }

    // 29-oct (pedido del usuario): el texto genérico con el código, para el formulario STS (allí sale en un aviso).
    private function mensajeProveedorExterno(): string
    {
        return self::MSG_PROVEEDOR_EXTERNO . ($this->codigoRespuesta !== null ? ' Cód. ' . $this->codigoRespuesta : '');
    }

    // 30-oct: la consulta del mes de Yupana sin perfil: pide SOLO el perfil (YupanaService::consultarPerfil, deja su fila PERFIL)
    // y lo guarda con crm.fn_cliente_consultas_perfil_actualizar. Devuelve null si quedó completa (o ya lo estaba), o el
    // código de la fila PERFIL si volvió a fallar. Nunca tumba la consulta.
    private function completarPerfilYupana(YupanaService $yupana, int $idConsulta, string $identificacion, int $tipoIdentificacion, string $canal, int $usuarioId, ?int $cliId, ?string $corredor, array $contexto): ?int
    {
        $json = $this->jsonConsultaGuardada($idConsulta);
        if (!$json || strtoupper((string) ($json['proveedor'] ?? '')) !== ConsultasService::PROVEEDOR_YUPANA || is_array($json['perfil'] ?? null)) {
            return null;
        }

        try {
            $perfil = $yupana->consultarPerfil($identificacion, $json['datos']['idCliente'] ?? null, [
                'canal' => $canal,
                'usu_id' => $usuarioId,
                'cli_id' => $cliId,
                'corredor' => $corredor,
                'agencia_codigo' => $this->decisionProveedor['agencia_codigo'] ?? null,
                'tipo_identificacion' => $tipoIdentificacion === 1 ? 'CEDULA' : 'RUC',
                'consulta_id' => $idConsulta,
            ]);

            DB::transaction(function () use ($idConsulta, $perfil, $contexto) {
                DB::selectOne('SELECT crm.fn_cliente_consultas_perfil_actualizar(?::jsonb) AS resultado', [json_encode([
                    'consulta_id' => $idConsulta,
                    'perfil' => $perfil,
                    'auditoria' => $contexto['auditoria'],
                ], JSON_UNESCAPED_UNICODE)]);
            });

            return null;
        } catch (Throwable $e) {
            return $yupana->idUltimaRespuesta();
        }
    }

    // 30-oct: los nombres de provincia, cantón y parroquia por sus ids (el alta por consulta trabaja con nombres).
    private function nombresGeo(int $prvId, int $ctnId, int $prqId): array
    {
        $f = DB::selectOne('SELECT (SELECT prv_nombre FROM public.provincia WHERE prv_id = ?) AS provincia,
                                   (SELECT ctn_nombre FROM public.canton WHERE ctn_id = ?) AS canton,
                                   (SELECT prq_nombre FROM public.parroquia WHERE prq_id = ?) AS parroquia', [$prvId, $ctnId, $prqId]);

        return ['provincia' => $f->provincia ?? null, 'canton' => $f->canton ?? null, 'parroquia' => $f->parroquia ?? null];
    }

    // 30-oct: provincia / cantón / parroquia completas a partir de lo que llegue del modal (ids o vacío), con la regla de
    // crm.fn_direccion_ubicacion_completar y sin dirección actual (la persona todavía no es cliente). 0 = no se pudo.
    private function ubicacionCompletar(array $direccion): array
    {
        $id = fn ($valor) => (int) ($valor ?? 0) > 0 ? (int) $valor : null;
        $f = DB::selectOne('SELECT crm.fn_direccion_ubicacion_completar(?::bigint, ?::bigint, ?::bigint, NULL::integer) AS geo', [
            $id($direccion['dir_prv_id'] ?? null), $id($direccion['dir_ctn_id'] ?? null), $id($direccion['dir_prq_id'] ?? null),
        ]);
        $geo = json_decode((string) ($f->geo ?? '{}'), true) ?: [];

        return ['prv_id' => (int) ($geo['prv_id'] ?? 0), 'ctn_id' => (int) ($geo['ctn_id'] ?? 0), 'prq_id' => (int) ($geo['prq_id'] ?? 0)];
    }

    // 30-oct: el calificador que se consulta, decidido por la agencia (crm.fn_cliente_consultas_proveedor_decidir): el
    // cliente del multinivel (public.clientes_multinivel) con el de la agencia MULTINIVEL; si no, con el de la agencia
    // del usuario. null = la agencia no tiene calificador. La decisión completa queda en $this->decisionProveedor.
    // ANTES (29-oct): siempre GaranCheck.
    // 30-oct (fase 3b): con $agenciaForzada (el botón Calificar del caso) decide por esa agencia en vez de la del usuario.
    private function proveedorConsulta(?int $cliId, int $usuarioId): ?ProveedorConsulta
    {
        $fila = DB::selectOne('SELECT crm.fn_cliente_consultas_proveedor_decidir(?, ?, ?::text) AS decision', [$cliId, $usuarioId, $this->agenciaForzada]);
        $this->decisionProveedor = $fila && $fila->decision ? (json_decode($fila->decision, true) ?: []) : [];
        $this->proveedorUsado = $this->servicioPorNombre($this->decisionProveedor['proveedor'] ?? null);

        return $this->proveedorUsado;
    }

    // El servicio de un calificador por su nombre (GARANCHECK, YUPANA); null si no hay o no se conoce. Yupana necesita el
    // código de tienda y la agencia de la decisión.
    private function servicioPorNombre(?string $nombre): ?ProveedorConsulta
    {
        switch (strtoupper(trim((string) $nombre))) {
            case ConsultasService::PROVEEDOR_GARANCHECK:
                return new GarancheckService();
            case ConsultasService::PROVEEDOR_YUPANA:
                return new YupanaService($this->decisionProveedor['codigo_tienda'] ?? null, $this->decisionProveedor['agencia_codigo'] ?? null);
            default:
                return null;
        }
    }

    // 30-oct: el servicio que entiende el JSON de una consulta guardada: la del mes puede ser de OTRO calificador que el de
    // la agencia de hoy. Si no se reconoce, el actual; y si tampoco hay, GaranCheck (el formato histórico).
    private function servicioDeConsulta(int $idConsulta, ?ProveedorConsulta $actual): ProveedorConsulta
    {
        try {
            $fila = DB::selectOne('SELECT proveedor FROM crm.cliente_historial_consultas WHERE id = ?', [$idConsulta]);
            $servicio = $this->servicioPorNombre($fila->proveedor ?? null);
        } catch (Throwable $e) {
            $servicio = null;
        }

        return $servicio ?? $actual ?? new GarancheckService();
    }

    // 27-oct: el mensaje tal cual si es conocido (MENSAJES_CONOCIDOS, MENSAJES_ERROR, los MSG_* o PATRONES_MENSAJES_CONOCIDOS);
    // si no, MSG_ERROR_CONSULTA.
    private function mensajeParaNavegador(string $mensaje): string
    {
        $fijos = array_merge(self::MENSAJES_CONOCIDOS, array_values(self::MENSAJES_ERROR), [
            self::MSG_CONSULTA_EN_CURSO,
            self::MSG_SIN_IDENTIDAD_RECIENTE,
            self::MSG_ERROR_CONSULTA,
            self::MSG_SIN_CALIFICADOR,
        ]);
        if (in_array($mensaje, $fijos, true)) {
            return $mensaje;
        }

        foreach (self::PATRONES_MENSAJES_CONOCIDOS as $patron) {
            if (preg_match($patron, $mensaje) === 1) {
                return $mensaje;
            }
        }

        return self::MSG_ERROR_CONSULTA;
    }

    // 27-oct: true si la consulta guardada $idConsulta no trae identidad (el proveedor respondió sin civil / sri: no salen
    // nombres) y es de hace más de MINUTOS_SIN_IDENTIDAD minutos. Esa no se reusa como caché: se vuelve a consultar.
    // $proveedor = el calificador que hizo la consulta guardada (el que entiende su JSON), nunca null.
    private function consultaSinIdentidadVencida(ProveedorConsulta $proveedor, int $idConsulta, string $identificacion, int $tipoIdentificacion): bool
    {
        $fila = DB::selectOne('SELECT created_at < now() - make_interval(mins => ?) AS vencida FROM crm.cliente_historial_consultas WHERE id = ?',
            [self::MINUTOS_SIN_IDENTIDAD, $idConsulta]);
        if (!$fila || !$fila->vencida) {
            return false;
        }

        $json = $this->jsonConsultaGuardada($idConsulta);
        if (!$json) {
            return true;
        }

        try {
            $tipoSujeto = ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipoIdentificacion) ?: 'N';
            $proveedor->extraerDatosCliente($json, $tipoSujeto);

            return false;
        } catch (Throwable $e) {
            return true;
        }
    }

    // 27-oct: candado de SESIÓN de PostgreSQL por identificación (sus 10 primeros dígitos: la cédula y su RUC son
    // la misma persona). De sesión y no de transacción porque dura mientras se espera al proveedor, y ahí no hay
    // transacción abierta. Si el candado lo tiene otra consulta, se reintenta hasta ESPERA_CANDADO_SEGUNDOS (en las
    // pruebas se acorta con services.garancheck.espera_candado_segundos). Si PHP muere a la mitad, PostgreSQL lo
    // suelta solo al cerrarse la conexión.
    private function tomarCandadoConsulta(string $identificacion): bool
    {
        $limite = microtime(true) + (float) config('services.garancheck.espera_candado_segundos', self::ESPERA_CANDADO_SEGUNDOS);

        while (true) {
            $fila = DB::selectOne('SELECT pg_try_advisory_lock(hashtext(?), hashtext(?)) AS tomado', [self::CANDADO_CONSULTA, substr($identificacion, 0, 10)]);
            if ($fila && $fila->tomado) {
                return true;
            }
            if (microtime(true) >= $limite) {
                return false;
            }
            usleep(250000);
        }
    }

    private function soltarCandadoConsulta(string $identificacion): void
    {
        try {
            DB::selectOne('SELECT pg_advisory_unlock(hashtext(?), hashtext(?)) AS soltado', [self::CANDADO_CONSULTA, substr($identificacion, 0, 10)]);
        } catch (Throwable $e) {
            // Sin conexión (o con una transacción de afuera ya abortada): PostgreSQL lo suelta al cerrar la sesión.
        }
    }

    // 27-oct: clave de la caché negativa (Cache de Laravel) de una identificación sin datos de identidad.
    private function claveSinIdentidad(string $proveedor, string $identificacion): string
    {
        return 'consultas-cliente:sin-identidad:' . $proveedor . ':' . $identificacion;
    }

    // PLAN B cuando GaranCheck no atiende: cédula → Ecuador Legal, RUC → SRI. Se crea el cliente
    // con lo único que dan esas fuentes (el nombre, y el domicilio en el caso del SRI) y se lo
    // vincula al corredor, para que la caída del buró no le pare el trabajo.
    //
    // NO se escriben crm.cliente_historial_consultas ni cliente_resumen_consultas: son las tablas
    // del reporte del buró y aquí no hay evaluación que guardar. El rastro queda en
    // crm.consulta_identidad (lo escribe la propia fuente) y en clientes_multinivel_consultas.
    private function altaPorIdentidadOficial(
        string $identificacion,
        int $tipoIdentificacion,
        ?array $foto,
        ?string $corredor,
        ?int $diasCorredor,
        array $contexto,
        int $usuarioId,
        Throwable $falloProveedor,
        string $canal,
        ?string $motivo = null
    ) {
        // 30-oct: $motivo: por qué se vino al respaldo cuando no fue una falla del calificador (la agencia no tiene
        // calificador); va en la respuesta para el aviso y, si el respaldo también falla, es el mensaje.
        $esCedula = $tipoIdentificacion === 1;
        $nombreProveedor = $esCedula ? ConsultasService::PROVEEDOR_ECUADOR_LEGAL : ConsultasService::PROVEEDOR_SRI;
        $consultas = app(ConsultaIdentidadExternoController::class);

        try {
            // 29-oct: con el canal, para su fila en crm.proveedor_respuestas.
            $respuesta = $esCedula
                ? $consultas->consultarCedulaEcuadorLegal($identificacion, $usuarioId, $canal)
                : $consultas->consultarRucSri($identificacion, $usuarioId, $canal);

            $cuerpo = $respuesta->getData(true);

            if (($cuerpo['status'] ?? '') !== 'success') {
                throw new RuntimeException((string) ($cuerpo['message'] ?? 'La fuente de identidad no respondió.'));
            }

            $datos = ConsultasService::datosDesdeIdentidad(
                $cuerpo['data'] ?? [],
                $identificacion,
                $tipoIdentificacion,
                $nombreProveedor
            );
            // 31-oct (regla del usuario): la persona que ya existía conserva sus nombres también con el respaldo; la
            // respuesta lleva los mismos que quedan en la base.
            $datos = ConsultasService::conNombresDeLaEntidad($datos, $foto);
            // 01-nov: Ecuador Legal / SRI no traen domicilio: la dirección del canal ("SN - MULTINIVEL" en el formulario STS, "SN"
            // en el caso), con la ubicación por defecto. Antes quedaba "SN - MULTINIVEL" sin ubicación en los dos.
            $datos = ConsultasService::direccionDelCanal($datos, $corredor !== null);

            // Todo o nada: cliente + vinculación.
            $cliId = DB::transaction(function () use ($datos, $foto, $contexto, $corredor, $diasCorredor) {
                // $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto); // DESACTIVADO 31-oct
                // 31-oct: y su dirección, teléfono y correo (antes fn_clientes_registrar le ponía lo de la fuente oficial).
                $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto, false, true);

                if ($corredor !== null) {
                    ConsultasService::vincularCorredor($cliId, $corredor, ConsultasService::TIPO_CORREDOR_STS, $diasCorredor);
                }

                return $cliId;
            });
        } catch (Throwable $e) {
            // Se cayeron las dos fuentes: no se crea nada. Se reporta el fallo de GaranCheck, que
            // es el principal, y el de la fuente de respaldo queda en data.
            // 29-oct (pedido del usuario): solo lo usa el formulario STS: el texto genérico con el código de la fila de
            // GaranCheck; el detalle de las dos fuentes está en crm.proveedor_respuestas.
            return response()->json(RespuestaApi::returnResultado(
                'error',
                // $this->mensajeError($falloProveedor, 'Error al consultar el cliente.'),
                $motivo ?? $this->mensajeProveedorExterno(),
                $e->getMessage()
            ));
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Consulta realizada con éxito', [
            'cli_id' => $cliId,
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'nombre_comercial' => $datos['nombre_comercial'],
            'tipo_sujeto' => $datos['tipo_sujeto'],
            'proveedor' => $nombreProveedor,
            // Sin buró no hay nada que mostrar: el front se apoya en esto para no abrir el modal.
            'evaluacion' => null,
            'es_cache' => false,
            'cliente_actualizado' => true,
            // 30-oct: por qué no hay calificación (la agencia sin calificador) y el código de la fila del calificador que
            // falló, para el aviso con Reintentar.
            'motivo' => $motivo,
            'codigo_respuesta' => $this->codigoRespuesta,
        ]));
    }

    // 1 = cédula, 2 = RUC (mismos valores que ent_tipo_identificacion), null si no valida.
    private function tipoIdentificacion(string $identificacion): ?int
    {
        if (preg_match('/^\d{10}$/', $identificacion) && ValidacionCedulaRucService::esCedulaValida($identificacion)) {
            return 1;
        }
        if (preg_match('/^\d{13}$/', $identificacion) && ValidacionCedulaRucService::esRucValido($identificacion)) {
            return 2;
        }

        return null;
    }

    // Ficha completa del cliente (o de la persona sin cliente), o null si no existe.
    private function fotoCliente(string $identificacion, int $tipoIdentificacion): ?array
    {
        $fila = DB::selectOne('SELECT datos FROM crm.fn_cliente_buscar_por_identificacion(?, ?)', [$identificacion, $tipoIdentificacion]);

        if (!$fila || empty($fila->datos)) {
            return null;
        }

        return json_decode($fila->datos, true);
    }

    // Qué caché manda. DOS parámetros, se elige por 'activar' y solo uno debería estar activo; si
    // los dos lo están gana el mensual. Ninguno activo (o días <= 0) = siempre en vivo.
    //   CONSULTA-CACHE          -> N días
    //   CONSULTA-CACHE-MENSUAL  -> N días CON tope de mes calendario (al cambiar de mes ya no vale)
    // Devuelve ['modo' => 'MENSUAL'|'DIAS'|'SIN_CACHE', 'dias' => int, 'por_mes' => bool].
    // 29-oct (pedido del usuario): antes que los dos manda CONSULTA-UNA-POR-MES: activo = una sola consulta por mes
    // calendario, sin días (modo 'UNA_POR_MES'). Los otros dos se quedan como estaban, apagados.
    private function modoCache(): array
    {
        $unaPorMes = DB::table('crm.parametro')->where('abreviacion', 'CONSULTA-UNA-POR-MES')->first();
        if ($unaPorMes && $unaPorMes->activar) {
            return ['modo' => 'UNA_POR_MES', 'dias' => 0, 'por_mes' => true];
        }

        $mensual = DB::table('crm.parametro')->where('abreviacion', 'CONSULTA-CACHE-MENSUAL')->first();
        $porMes = $mensual && $mensual->activar;

        $parametro = $porMes ? $mensual : DB::table('crm.parametro')->where('abreviacion', 'CONSULTA-CACHE')->first();
        $dias = $parametro && $parametro->activar ? (int) trim((string) $parametro->valor) : 0;

        if ($dias <= 0) {
            return ['modo' => 'SIN_CACHE', 'dias' => 0, 'por_mes' => false];
        }

        return ['modo' => $porMes ? 'MENSUAL' : 'DIAS', 'dias' => $dias, 'por_mes' => $porMes];
    }

    // 28-oct: la consulta guardada que se reusa como caché, o null si hay que ir al proveedor. Es LA regla de la caché
    // (procesarConsulta y cacheCliente): la última consulta real vigente del cliente (cacheVigente)...
    private function idCacheConsulta(?int $cliId, ?ProveedorConsulta $proveedor, array $cache, string $identificacion, int $tipoIdentificacion): ?int
    {
        if ($cliId === null) {
            return null;
        }

        $idCache = $this->cacheVigente($cliId, $proveedor ? $proveedor->nombre() : null, $cache);
        // 27-oct: ...pero una consulta guardada SIN identidad solo se reusa MINUTOS_SIN_IDENTIDAD minutos (la fuente del
        // proveedor pudo caerse solo un rato); después se vuelve a consultar.
        // 30-oct (lo destapó la prueba E2E del tipo 190): el JSON guardado lo lee el calificador que HIZO esa consulta
        // (servicioDeConsulta), no el de la agencia de hoy: la del mes puede ser de otro calificador, o la agencia no tener
        // ninguno. Con el de la agencia, un JSON de GaranCheck leído por Yupana "no tenía identidad", la consulta del mes se
        // daba por vencida y se iba al proveedor en vivo (cobraba) pese a CONSULTA-UNA-POR-MES.
        if ($idCache !== null && $this->consultaSinIdentidadVencida($this->servicioDeConsulta($idCache, $proveedor), $idCache, $identificacion, $tipoIdentificacion)) {
            return null;
        }

        return $idCache;
    }

    // id de la última consulta real del cliente con este proveedor dentro de la vigencia, o null.
    private function cacheVigente(int $cliId, ?string $proveedor, array $cache): ?int
    {
        if ($cache['modo'] === 'SIN_CACHE') {
            return null;
        }

        // 29-oct: la consulta del mes calendario. 30-oct (decisión del usuario): de CUALQUIER calificador (NULL): la del mes
        // se reusa aunque la agencia de hoy tenga otro, y también cuando la agencia no tiene ninguno.
        if ($cache['modo'] === 'UNA_POR_MES') {
            $fila = DB::selectOne('SELECT crm.fn_cliente_consultas_buscar_cache_mes(?, ?) AS id', [$cliId, null]);

            return !empty($fila->id) ? (int) $fila->id : null;
        }

        // Los modos viejos (apagados) siguen por proveedor: sin calificador no hay caché.
        if ($proveedor === null) {
            return null;
        }

        $fila = DB::selectOne('SELECT crm.fn_cliente_consultas_buscar_cache_existente(?, ?, ?, ?) AS id', [$cliId, $proveedor, $cache['dias'], $cache['por_mes']]);

        return !empty($fila->id) ? (int) $fila->id : null;
    }

    // 27-oct: datos del proveedor para PRECARGAR el modal del cliente (campo cliente de Nuevo Caso), o null
    // si no salen. Nunca tumba la consulta: ya está guardada (y pagada) y el front abre el modal sin precarga.
    // cliente_nuevo: true = el modal le pone todo lo que casa; false = solo lo que el cliente tiene vacío.
    private function datosPrecarga(ProveedorConsulta $proveedor, ?array $json, string $identificacion, int $tipoIdentificacion, bool $clienteNuevo): ?array
    {
        if (!$json) {
            return null;
        }

        try {
            $tipoSujeto = ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipoIdentificacion) ?: 'N';

            return $proveedor->extraerDatosPrecarga($json, $tipoSujeto) + ['cliente_nuevo' => $clienteNuevo];
        } catch (Throwable $e) {
            return null;
        }
    }

    // 28-oct: la evaluación de una consulta guardada, como la ve el STS (la misma que arma la pestaña del caso), o null.
    // Nunca tumba la respuesta: la consulta ya está guardada.
    private function evaluacionGuardada(ProveedorConsulta $proveedor, ?array $json): ?array
    {
        if (!$json) {
            return null;
        }

        try {
            return $proveedor->evaluacionParaPantalla($json);
        } catch (Throwable $e) {
            return null;
        }
    }

    // 28-oct: la fecha de la consulta ORIGINAL y el nombre del cliente, iguales a los de la pestaña del caso
    // (crm.fn_caso_consulta_garancheck), para el modal de calificación de Nuevo Caso. Si no salen, van null.
    private function fechaYNombreConsulta(int $idConsultaOrigen, $cliId): array
    {
        try {
            $fila = DB::selectOne("SELECT (SELECT to_jsonb(h.created_at) #>> '{}' FROM crm.cliente_historial_consultas h WHERE h.id = ?) AS fecha_consulta,
                (SELECT NULLIF(TRIM(CONCAT_WS(' ', TRIM(ent.ent_apellidos), TRIM(ent.ent_nombres))), '')
                 FROM public.cliente cli JOIN public.entidad ent ON ent.ent_id = cli.ent_id
                 WHERE cli.cli_id = ?) AS nombre_cliente", [$idConsultaOrigen, $cliId !== null ? (int) $cliId : null]);
        } catch (Throwable $e) {
            return ['fecha_consulta' => null, 'nombre_cliente' => null];
        }

        return [
            'fecha_consulta' => $fila->fecha_consulta ?? null,
            'nombre_cliente' => $fila->nombre_cliente ?? null,
        ];
    }

    // 27-oct: JSON completo de una consulta guardada (la que se reusa por caché), o null si no se puede leer.
    private function jsonConsultaGuardada(int $idConsulta): ?array
    {
        try {
            $fila = DB::selectOne('SELECT valor FROM crm.cliente_historial_consultas WHERE id = ?', [$idConsulta]);
        } catch (Throwable $e) {
            return null;
        }

        $json = $fila && $fila->valor !== null ? json_decode($fila->valor, true) : null;

        return is_array($json) ? $json : null;
    }

    // Historial + resumen + forense en una sola función. Devuelve {id, calificacion_cliente, score}.
    private function registrarConsulta(array $payload): array
    {
        $fila = DB::selectOne('SELECT crm.fn_cliente_consultas_registrar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

        return json_decode($fila->resultado, true) ?: [];
    }

    // Traduce el código de excepción de las funciones PG al mensaje del usuario; las excepciones
    // propias (RuntimeException del servicio) ya traen su mensaje; el resto va al genérico.
    private function mensajeError(Throwable $e, string $mensajeGenerico): string
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return $mensaje;
            }
        }

        if (!$e instanceof QueryException && $e->getMessage() !== '') {
            return $e->getMessage();
        }

        return $mensajeGenerico;
    }
}
