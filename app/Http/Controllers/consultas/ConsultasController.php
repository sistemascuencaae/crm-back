<?php

namespace App\Http\Controllers\consultas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\varios\ConsultaIdentidadExternoController;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Consultas\ConsultasService;
use App\Servicios\Consultas\GarancheckService;
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
    ];

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

        return $this->respuestaNuevoCaso($this->procesarConsulta($request, trim($request->input('identificacion')), null));
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
            $idCache = $this->idCacheConsulta($cliId, new GarancheckService(), $this->modoCache(), $identificacion, $tipoIdentificacion);

            return response()->json(RespuestaApi::returnResultado('success', 'Caché revisada.', ['cache' => $idCache !== null]));
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

        return $this->procesarConsulta($request, trim($request->input('identificacion')), trim($request->input('corredor')));
    }

    // Mismo flujo para los tres canales; $corredor decide si hay vinculación multinivel.
    // $usuId: canal SIN JWT (el formulario público del corredor, que lo saca del token cifrado del
    // enlace). Pública para que DynamoClienteController la llame desde la lupa del formulario.
    // $conRespaldo: si GaranCheck no atiende, se crea igual al cliente con la fuente de identidad
    // oficial (Ecuador Legal / SRI), sin evaluación. Solo lo usa el formulario del corredor, para
    // que una caída del buró no le impida casar clientes; el canal del CRM sigue fallando duro.
    public function procesarConsulta(Request $request, string $identificacion, ?string $corredor, ?int $usuId = null, bool $conRespaldo = false)
    {
        // 27-oct: candado por identificación; se suelta en el finally.
        $conCandado = false;

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

            $proveedor = new GarancheckService();
            $contexto = ConsultasService::contextoAuditoria($request, $corredor, $usuId);
            $base = [
                'usu_id' => $usuarioId,
                'proveedor' => $proveedor->nombre(),
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

                    $resultado += [
                        'es_cache' => true,
                        'cliente_actualizado' => false,
                        'motivo' => null,
                        'cache' => $cache,
                    ];

                    // 27-oct: con la caché no hay JSON nuevo; la precarga sale de la consulta guardada que se reusa.
                    if ($conPrecarga) {
                        $jsonCache = $this->jsonConsultaGuardada($idCache);
                        $resultado['datos_cliente'] = $this->datosPrecarga($proveedor, $jsonCache, $identificacion, $tipoIdentificacion,
                            ConsultasService::clienteSinGuardar($cliId, $proveedor->nombre()));

                        // 28-oct: el modal de calificación de Nuevo Caso: la evaluación de la consulta guardada (sin ir al
                        // proveedor), con su fecha y el nombre del cliente. Si una política crítica tumbó la Evaluación de
                        // Fuentes, el resultado general dice lo mismo que en vivo.
                        $resultado['evaluacion'] = $this->evaluacionGuardada($proveedor, $jsonCache);
                        $resultado += $this->fechaYNombreConsulta($idCache, $cliId);
                        $criticas = $resultado['evaluacion']['evaluacionGeneral']['rechazoAlmespana'] ?? [];
                        if ($criticas) {
                            $resultado['resultado_general_proveedor'] = $resultado['resultado_general'] ?? null;
                            $resultado['resultado_general'] = false;
                            $resultado['politicas_criticas'] = $criticas;
                        }
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

            try {
                if ($cliId === null) {
                    $sinIdentidadReciente = Cache::get($this->claveSinIdentidad($proveedor->nombre(), $identificacion));
                    if ($sinIdentidadReciente !== null) {
                        throw new RuntimeException(self::MSG_SIN_IDENTIDAD_RECIENTE);
                    }
                }

                $json = $proveedor->consultar($identificacion);

                $tipoSujeto = ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipoIdentificacion) ?: 'N';
                try {
                    $datos = $proveedor->extraerDatosCliente($json, $tipoSujeto) + [
                        'identificacion' => $identificacion,
                        'tipo_identificacion' => $tipoIdentificacion,
                        'proveedor' => $proveedor->nombre(),
                    ];
                } catch (Throwable $e) {
                    if ($cliId === null) {
                        Cache::put($this->claveSinIdentidad($proveedor->nombre(), $identificacion), $e->getMessage(), now()->addMinutes(self::MINUTOS_SIN_IDENTIDAD));
                        throw $e;
                    }
                    $motivoSinIdentidad = self::MSG_SIN_IDENTIDAD_EXISTENTE;
                }
            } catch (Throwable $e) {
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
                    $falloProveedor
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
                    $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto, $corredor === null);
                    $fichaActualizada = true;
                    $clienteNuevo = true;
                } elseif ($datos === null) {
                    // 27-oct: 200 sin identidad: la consulta se guarda igual y los nombres no se tocan.
                    $motivo = $motivoSinIdentidad;
                } else {
                    $clienteNuevo = ConsultasService::clienteSinGuardar($cliId, $proveedor->nombre());
                    try {
                        // 27-oct: SOLO nombres, apellidos y nombre comercial, y solo si cambian (antes la ficha entera
                        // pasaba por fn_clientes_modificar). En la respuesta van los nombres que quedaron en la base.
                        // En el canal del CRM también sella la consulta de identidad.
                        $nombres = DB::transaction(function () use ($cliId, $datos, $contexto, $corredor) {
                            return ConsultasService::actualizarNombresCliente($cliId, $datos, $contexto, $corredor === null);
                        });
                        $fichaActualizada = (bool) ($nombres['cliente_actualizado'] ?? false);
                        $motivo = $nombres['motivo'] ?? null;
                        $datos['apellidos'] = $nombres['ent_apellidos'] ?? $datos['apellidos'];
                        $datos['nombres'] = $nombres['ent_nombres'] ?? $datos['nombres'];
                        $datos['nombre_comercial'] = $nombres['ent_nombre_comercial'] ?? $datos['nombre_comercial'];
                    } catch (Throwable $e) {
                        // La consulta se guarda igual.
                        $motivo = $this->mensajeError($e, 'No se pudo actualizar la ficha del cliente.');
                    }
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
            // Nada guardado (las transacciones ya revirtieron). El mensaje crudo va en data, como en el corredor.
            return response()->json(RespuestaApi::returnResultado('error', $this->mensajeError($e, 'Error al consultar el cliente.'), $e->getMessage()));
        } finally {
            if ($conCandado) {
                $this->soltarCandadoConsulta($identificacion);
            }
        }
    }

    // 27-oct: lo que ve el navegador en el canal del CRM: CAMPOS_NUEVO_CASO (la evaluación del buró, nombres y cli_id
    // se quedan en el servidor). 28-oct: la evaluación ya sale, para el modal de calificación; cli_id y el JSON no. En un error tampoco va el mensaje crudo de la excepción (puede traer el SQL con el
    // JSON de la consulta), y el mensaje solo si es uno conocido (mensajeParaNavegador).
    private function respuestaNuevoCaso(JsonResponse $respuesta): JsonResponse
    {
        $cuerpo = $respuesta->getData(true);
        $data = $cuerpo['data'] ?? null;
        $exito = ($cuerpo['status'] ?? '') === 'success';

        $cuerpo['data'] = $exito && is_array($data)
            ? array_intersect_key($data, array_flip(self::CAMPOS_NUEVO_CASO))
            : null;

        if (!$exito) {
            $cuerpo['message'] = $this->mensajeParaNavegador((string) ($cuerpo['message'] ?? ''));
        }

        return response()->json($cuerpo, $respuesta->getStatusCode());
    }

    // 27-oct: el mensaje tal cual si es conocido (MENSAJES_CONOCIDOS, MENSAJES_ERROR, los MSG_* o PATRONES_MENSAJES_CONOCIDOS);
    // si no, MSG_ERROR_CONSULTA.
    private function mensajeParaNavegador(string $mensaje): string
    {
        $fijos = array_merge(self::MENSAJES_CONOCIDOS, array_values(self::MENSAJES_ERROR), [
            self::MSG_CONSULTA_EN_CURSO,
            self::MSG_SIN_IDENTIDAD_RECIENTE,
            self::MSG_ERROR_CONSULTA,
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
    private function consultaSinIdentidadVencida(GarancheckService $proveedor, int $idConsulta, string $identificacion, int $tipoIdentificacion): bool
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
        Throwable $falloProveedor
    ) {
        $esCedula = $tipoIdentificacion === 1;
        $nombreProveedor = $esCedula ? ConsultasService::PROVEEDOR_ECUADOR_LEGAL : ConsultasService::PROVEEDOR_SRI;
        $consultas = app(ConsultaIdentidadExternoController::class);

        try {
            $respuesta = $esCedula
                ? $consultas->consultarCedulaEcuadorLegal($identificacion, $usuarioId)
                : $consultas->consultarRucSri($identificacion, $usuarioId);

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

            // Todo o nada: cliente + vinculación.
            $cliId = DB::transaction(function () use ($datos, $foto, $contexto, $corredor, $diasCorredor) {
                $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto);

                if ($corredor !== null) {
                    ConsultasService::vincularCorredor($cliId, $corredor, ConsultasService::TIPO_CORREDOR_STS, $diasCorredor);
                }

                return $cliId;
            });
        } catch (Throwable $e) {
            // Se cayeron las dos fuentes: no se crea nada. Se reporta el fallo de GaranCheck, que
            // es el principal, y el de la fuente de respaldo queda en data.
            return response()->json(RespuestaApi::returnResultado(
                'error',
                $this->mensajeError($falloProveedor, 'Error al consultar el cliente.'),
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
            'motivo' => null,
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
    private function modoCache(): array
    {
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
    private function idCacheConsulta(?int $cliId, GarancheckService $proveedor, array $cache, string $identificacion, int $tipoIdentificacion): ?int
    {
        if ($cliId === null) {
            return null;
        }

        $idCache = $this->cacheVigente($cliId, $proveedor->nombre(), $cache);
        // 27-oct: ...pero una consulta guardada SIN identidad solo se reusa MINUTOS_SIN_IDENTIDAD minutos (la fuente del
        // proveedor pudo caerse solo un rato); después se vuelve a consultar.
        if ($idCache !== null && $this->consultaSinIdentidadVencida($proveedor, $idCache, $identificacion, $tipoIdentificacion)) {
            return null;
        }

        return $idCache;
    }

    // id de la última consulta real del cliente con este proveedor dentro de la vigencia, o null.
    private function cacheVigente(int $cliId, string $proveedor, array $cache): ?int
    {
        if ($cache['modo'] === 'SIN_CACHE') {
            return null;
        }

        $fila = DB::selectOne('SELECT crm.fn_cliente_consultas_buscar_cache_existente(?, ?, ?, ?) AS id', [$cliId, $proveedor, $cache['dias'], $cache['por_mes']]);

        return !empty($fila->id) ? (int) $fila->id : null;
    }

    // 27-oct: datos del proveedor para PRECARGAR el modal del cliente (campo cliente de Nuevo Caso), o null
    // si no salen. Nunca tumba la consulta: ya está guardada (y pagada) y el front abre el modal sin precarga.
    // cliente_nuevo: true = el modal le pone todo lo que casa; false = solo lo que el cliente tiene vacío.
    private function datosPrecarga(GarancheckService $proveedor, ?array $json, string $identificacion, int $tipoIdentificacion, bool $clienteNuevo): ?array
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
    private function evaluacionGuardada(GarancheckService $proveedor, ?array $json): ?array
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
