<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Consultas\GarancheckService;
use Illuminate\Support\Facades\DB;
use Throwable;

// 27-oct: la consulta de GaranCheck con la que se creó el caso (crm.caso.cliente_consulta_id), para los badges de la
// cabecera del caso y su pestaña Calificación. La casa crm.fn_caso_vincular_consulta al crear el caso y la lee
// crm.fn_caso_consulta_garancheck (PLANES/GARANCHECK/produccion_garancheck_caso.sql).
class CasoConsultaGarancheckController extends Controller
{
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
            $fila = DB::selectOne('SELECT crm.fn_caso_consulta_garancheck(?) AS datos', [(int) $casoId]);
            $datos = $fila && $fila->datos !== null ? json_decode($fila->datos, true) : null;

            if (!is_array($datos)) {
                return response()->json(RespuestaApi::returnResultado('success', 'El caso no tiene consulta de GaranCheck.', null));
            }

            $valor = $datos['valor'] ?? null;
            unset($datos['valor']);

            // La misma evaluación que ve el corredor en el STS (fecha de corte, instituciones y políticas críticas).
            $datos['evaluacion'] = is_array($valor) ? (new GarancheckService())->evaluacionParaPantalla($valor) : null;

            // Como en ConsultasController::procesarConsulta: si una política crítica tumbó la Evaluación de Fuentes, el
            // resultado general de la cabecera dice lo mismo que la pestaña. Lo guardado conserva el del proveedor.
            $criticas = $datos['evaluacion']['evaluacionGeneral']['rechazoAlmespana'] ?? [];
            if ($criticas && isset($datos['resumen']) && is_array($datos['resumen'])) {
                $datos['resumen']['resultado_general_proveedor'] = $datos['resumen']['resultado_general'] ?? null;
                $datos['resumen']['resultado_general'] = false;
                $datos['resumen']['politicas_criticas'] = $criticas;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Consulta de GaranCheck del caso.', $datos));
        } catch (Throwable $e) {
            $log->logError(CasoConsultaGarancheckController::class, 'Error al leer la consulta GaranCheck del caso #' . $casoId, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la consulta de GaranCheck del caso.', null));
        }
    }
}
