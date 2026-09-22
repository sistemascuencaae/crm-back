<?php

namespace App\Http\Controllers\consultas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Consultas\ConsultasService;
use App\Servicios\Consultas\GarancheckService;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

    // Version 1.0
    // POST /api/consultas/cliente — la consulta la hace un usuario del CRM (corredor NULL).
    public function consultarCliente(Request $request)
    {
        return $this->procesarConsulta($request, trim((string) $request->input('identificacion')), null);
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
    public function procesarConsulta(Request $request, string $identificacion, ?string $corredor, ?int $usuId = null)
    {
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

            // 4. Caché por cliente (parámetro CONSULTA-CACHE, días; 0 = siempre en vivo). Con una
            // consulta vigente se copia desde el historial: no se va al proveedor ni se toca al cliente.
            $cache = $this->modoCache();

            if ($cliId !== null) {
                $idCache = $this->cacheVigente($cliId, $proveedor->nombre(), $cache);
                if ($idCache !== null) {
                    $resultado = DB::transaction(function () use ($base, $cliId, $idCache, $corredor, $diasCorredor) {
                        $registro = $this->registrarConsulta($base + ['cli_id' => $cliId, 'es_cache' => true, 'id_cache' => $idCache, 'valor' => null]);
                        if ($corredor !== null) {
                            ConsultasService::vincularCorredor($cliId, $corredor, ConsultasService::TIPO_CORREDOR_STS, $diasCorredor);
                        }

                        return $registro;
                    });

                    return response()->json(RespuestaApi::returnResultado('success', 'Consulta obtenida del historial', $resultado + [
                        'es_cache' => true,
                        'cliente_actualizado' => false,
                        'motivo' => null,
                        'cache' => $cache,
                    ]));
                }
            }

            // 5. Proveedor. Si lanza (sin credenciales, red, HTTP, error del catálogo) se responde el
            // error y NO se guarda nada.
            $json = $proveedor->consultar($identificacion);

            // 6. Datos mínimos del cliente. El tipo de persona lo decide el número, no el JSON.
            $tipoSujeto = ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, $tipoIdentificacion) ?: 'N';
            $datos = $proveedor->extraerDatosCliente($json, $tipoSujeto) + [
                'identificacion' => $identificacion,
                'tipo_identificacion' => $tipoIdentificacion,
            ];

            // 7. Todo o nada: cliente + consulta + vinculación en la misma transacción. La actualización
            // del cliente existente es best-effort dentro de un SAVEPOINT (transacción anidada).
            $resultado = DB::transaction(function () use ($base, $foto, $cliId, $datos, $json, $contexto, $corredor, $diasCorredor, $proveedor) {
                $fichaActualizada = false;
                $motivo = null;

                if ($cliId === null) {
                    $cliId = ConsultasService::registrarCliente($datos, $foto, $contexto);
                    $fichaActualizada = true;
                } else {
                    try {
                        DB::transaction(function () use ($foto, $datos, $contexto) {
                            ConsultasService::actualizarNombresCliente($foto, $datos, $contexto);
                        });
                        $fichaActualizada = true;
                    } catch (Throwable $e) {
                        // Típico: cliente a CRÉDITO con ficha incompleta. La consulta se guarda igual.
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
                    // Para el formulario del corredor: qué cliente quedó y con qué nombre.
                    'cli_id' => $cliId,
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
        }
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

    // id de la última consulta real del cliente con este proveedor dentro de la vigencia, o null.
    private function cacheVigente(int $cliId, string $proveedor, array $cache): ?int
    {
        if ($cache['modo'] === 'SIN_CACHE') {
            return null;
        }

        $fila = DB::selectOne('SELECT crm.fn_cliente_consultas_buscar_cache_existente(?, ?, ?, ?) AS id', [$cliId, $proveedor, $cache['dias'], $cache['por_mes']]);

        return !empty($fila->id) ? (int) $fila->id : null;
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
