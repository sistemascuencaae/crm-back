<?php

namespace App\Http\Controllers\crm;

use App\Events\TableroEvent;
use App\Http\Controllers\consultas\ConsultasController;
use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Consultas\GarancheckService;
use App\Servicios\Consultas\YupanaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

// 27-oct: la consulta de GaranCheck con la que se creó el caso (crm.caso.cliente_consulta_id), para los badges de la
// cabecera del caso y su pestaña Calificación. La casa crm.fn_caso_vincular_consulta al crear el caso y la lee
// crm.fn_caso_consulta_garancheck (PLANES/GARANCHECK/produccion_garancheck_caso.sql).
// 30-oct: de cualquier calificador (GaranCheck o Yupana): crm.fn_caso_consulta_buscar (PLANES/YUPANA/produccion_yupana.sql).
// 30-oct (fase 3b): también el botón Calificar / Reintentar del caso (calificar).
class CasoConsultaGarancheckController extends Controller
{
    private const MSG_SIN_CONSULTA = 'El caso no tiene consulta de calificación.';

    // 06-nov: los códigos de crm.fn_caso_calificar_validaciones.
    private const MENSAJES_ERROR = [
        'CASO_NO_EXISTE' => 'El caso no existe.',
        'CASO_YA_CALIFICADO' => 'Este caso ya tiene calificación y no se puede cambiar.',
        'CASO_SIN_IDENTIFICACION' => 'El caso no tiene la identificación del cliente; no se puede calificar.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/crm/casoConsultaGarancheck/{caso_id}. La ve quien puede ver el caso (el mismo JWT de las rutas del caso).
    // data = null si el caso no tiene consulta casada. Si la tiene: fechas, identificación, nombre, el resumen de la
    // cabecera y la evaluación tal como la muestra el modal del STS. El JSON del proveedor no sale del servidor.
    public function mostrar($casoId)
    {
        $log = new Funciones();

        try {
            $datos = $this->consultaDelCaso((int) $casoId);

            if ($datos === null) {
                return response()->json(RespuestaApi::returnResultado('success', self::MSG_SIN_CONSULTA, null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Consulta de calificación del caso.', $datos));
        } catch (Throwable $e) {
            $log->logError(CasoConsultaGarancheckController::class, 'Error al leer la consulta de calificación del caso #' . $casoId, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la calificación del caso.', null));
        }
    }

    // Version 1.0
    // POST /api/crm/casoCalificar/{caso_id} — 30-oct (fase 3b): el botón Calificar / Reintentar de la cabecera del caso y
    // de la pestaña Calificación. 1) Consulta al calificador de la AGENCIA DEL CASO (o MULTINIVEL) reusando la consulta del
    // mes (si hay que pagar, paga una; sin respaldo: el cliente ya existe). 2) Con o sin éxito vuelve a casar el caso con la
    // consulta vigente y guarda el texto en crm.caso.calificacion_proveedor (crm.fn_caso_calificar): una consulta del mes
    // hecha por otro usuario o el perfil de Yupana que llegó después también cuentan. 3) Avisa al tablero con el caso
    // completo (TableroEvent) y lo devuelve con la consulta para refrescar la cabecera y la pestaña. Si el calificador falla,
    // status error con el motivo y su Cód., pero data trae igual el caso.
    // 06-nov (regla del usuario): la calificación que el caso ya tiene queda fija, es su histórico. Se valida ANTES de consultar
    // para no llamar al calificador ni cobrar; un caso ya calificado responde error y data trae el caso, para que una pantalla
    // abierta desde antes se ponga al día y deje de ofrecer Reintentar.
    public function calificar(Request $request, $casoId)
    {
        $log = new Funciones();
        $casoId = (int) $casoId;

        try {
            DB::transaction(fn () => DB::selectOne('SELECT crm.fn_caso_calificar_validaciones(?)', [$casoId]));

            $caso = DB::selectOne('SELECT identificacion, codigo_agencia FROM crm.caso WHERE id = ?', [$casoId]);
            $identificacion = trim((string) $caso->identificacion);

            // 1. La consulta, con el calificador de la agencia del caso.
            $respuesta = app(ConsultasController::class)->consultarParaCaso($request, $identificacion, $caso->codigo_agencia !== null ? (string) $caso->codigo_agencia : null);
            $cuerpo = $respuesta->getData(true);
            $consultaOk = ($cuerpo['status'] ?? '') === 'success';
            $codigo = $cuerpo['data']['codigo_respuesta'] ?? null;

            // 2. Casar y guardar la calificación.
            $fila = DB::selectOne('SELECT crm.fn_caso_calificar(?) AS resultado', [$casoId]);
            $calificacion = $fila && $fila->resultado ? (json_decode($fila->resultado, true) ?: []) : [];
            $texto = $calificacion['calificacion_proveedor'] ?? null;

            // 06-nov: con el score de la consulta se llenan los requerimientos automáticos que quedaron sin resultado.
            app(CasoController::class)->calcularRequerimientosAutomaticos($casoId);

            // 3. El caso completo: para la tarjeta de todos (TableroEvent) y para el navegador que apretó el botón.
            $casoCompleto = app(CasoController::class)->getCaso($casoId);
            try {
                broadcast(new TableroEvent($casoCompleto));
            } catch (Throwable $e) {
                $log->logError(CasoConsultaGarancheckController::class, 'No se pudo avisar al tablero la calificación del caso #' . $casoId, $e);
            }

            $data = [
                'caso' => $casoCompleto,
                'calificacion' => $calificacion,
                'consulta' => $this->consultaDelCaso($casoId),
                'consulta_ok' => $consultaOk,
                'consulta_mensaje' => $cuerpo['message'] ?? null,
                'codigo_respuesta' => $codigo,
            ];
            $sufijoCodigo = $codigo ? ' Cód. ' . $codigo : '';

            if (!$consultaOk) {
                return response()->json(RespuestaApi::returnResultado('error', ($cuerpo['message'] ?? 'No se pudo consultar al calificador.') . $sufijoCodigo, $data));
            }

            $mensaje = $texto !== null && $texto !== ''
                ? 'Cliente calificado: ' . $texto
                : 'La consulta se hizo, pero el calificador no devolvió la calificación.' . $sufijoCodigo;

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $data));
        } catch (Throwable $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    $data = $codigo === 'CASO_YA_CALIFICADO' ? ['caso' => app(CasoController::class)->getCaso($casoId)] : null;

                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, $data));
                }
            }

            $log->logError(CasoConsultaGarancheckController::class, 'Error al calificar el caso #' . $casoId, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo calificar el caso.', null));
        }
    }

    // La consulta casada con el caso como la muestra la pestaña Calificación (crm.fn_caso_consulta_buscar + la evaluación
    // armada por el servicio del calificador que la hizo), o null si el caso no tiene consulta.
    private function consultaDelCaso(int $casoId): ?array
    {
        // 30-oct: de cualquier calificador (GaranCheck o Yupana), con calificacion_texto según la regla del usuario.
        $fila = DB::selectOne('SELECT crm.fn_caso_consulta_buscar(?) AS datos', [$casoId]);
        $datos = $fila && $fila->datos !== null ? json_decode($fila->datos, true) : null;

        if (!is_array($datos)) {
            return null;
        }

        $valor = $datos['valor'] ?? null;
        unset($datos['valor']);

        // La misma evaluación que ve el corredor en el STS (fecha de corte, instituciones y políticas críticas).
        // 30-oct: el JSON lo entiende el servicio del calificador que hizo la consulta.
        $servicio = strtoupper((string) ($datos['proveedor'] ?? '')) === 'YUPANA' ? new YupanaService() : new GarancheckService();
        $datos['evaluacion'] = is_array($valor) ? $servicio->evaluacionParaPantalla($valor) : null;

        // Como en ConsultasController::procesarConsulta: si una política crítica tumbó la Evaluación de Fuentes, el
        // resultado general de la cabecera dice lo mismo que la pestaña. Lo guardado conserva el del proveedor.
        $criticas = $datos['evaluacion']['evaluacionGeneral']['rechazoAlmespana'] ?? [];
        if ($criticas && isset($datos['resumen']) && is_array($datos['resumen'])) {
            $datos['resumen']['resultado_general_proveedor'] = $datos['resumen']['resultado_general'] ?? null;
            $datos['resumen']['resultado_general'] = false;
            $datos['resumen']['politicas_criticas'] = $criticas;
        }

        return $datos;
    }
}
