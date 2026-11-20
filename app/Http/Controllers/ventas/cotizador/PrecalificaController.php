<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

// Version 1.0
// Precalifica del caso (ventas.precalifica, PLANES/COTIZADOR/produccion_precalifica.sql). Se calcula al crear el caso si su tipo
// la pide (después de la calificación) y otra vez cuando el Reintentar de GaranCheck sale bien, mientras no se elija el TIPO
// CRÉDITO. El TIPO CRÉDITO del caso (requerimiento con la marca TIPO_CREDITO) elige la fila y llena los 6 requerimientos del cotizador.
// 19-nov-2026.
class PrecalificaController extends Controller
{
    use AuditoriaForenseTrait;

    // Códigos de ventas.fn_precalifica_elegir.
    private const MENSAJES_ERROR = [
        'TIPO_CREDITO_REQUERIDO' => 'Elija el tipo de crédito.',
        'TIPO_CREDITO_YA_ELEGIDO' => 'El tipo de crédito ya está elegido y no se puede cambiar.',
        'CASO_SIN_CALIFICACION' => 'Primero hay que calificar al cliente.',
        'PRECALIFICA_NO_EXISTE' => 'Primero hay que precalificar al cliente.',
        'PRECALIFICA_SIN_TIPO_CREDITO' => 'La precalifica no tiene datos de :linea.',
    ];

    // Estados de ventas.fn_precalifica_calcular para el botón Reintentar.
    private const MENSAJES_CALCULO = [
        'OK' => 'Cliente precalificado.',
        'YA_EXISTE' => 'El caso ya tiene su precalifica.',
        'YA_ELEGIDA' => 'El tipo de crédito ya está elegido: la precalifica no cambia.',
        'NO_APLICA' => 'Este tipo de caso no pide precalifica.',
        'SIN_IDENTIFICACION' => 'El caso no tiene la identificación del cliente: no se puede precalificar.',
        'SIN_FILAS' => 'La precalifica no devolvió datos. Intente de nuevo.',
        'ERROR' => 'No se pudo precalificar al cliente. Intente de nuevo.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/crm/listPrecalifica/{caso_id}: las precalificas del caso para su pestaña y si se ofrece Reintentar.
    public function listPrecalifica($casoId)
    {
        try {
            $datos = $this->datosPestana((int) $casoId);

            if ($datos === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'El caso no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $datos));
        } catch (Throwable $e) {
            (new Funciones())->logError(PrecalificaController::class, 'Error al listar la precalifica del caso #' . $casoId, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la precalifica del caso.', null));
        }
    }

    // Version 1.0
    // POST /api/crm/reintentarPrecalifica/{caso_id}: el Reintentar de la pestaña (sale solo cuando la precalifica falló).
    public function reintentarPrecalifica(Request $request, $casoId)
    {
        $casoId = (int) $casoId;
        $estado = $this->calcular($casoId, false, false, $request)['estado'] ?? 'ERROR';
        $mensaje = self::MENSAJES_CALCULO[$estado] ?? self::MENSAJES_CALCULO['ERROR'];

        try {
            $datos = $this->datosPestana($casoId);
        } catch (Throwable $e) {
            $datos = null;
        }

        return response()->json(RespuestaApi::returnResultado($estado === 'OK' ? 'success' : 'error', $mensaje, $datos));
    }

    // Calcula la precalifica del caso: al crearlo (iniciar copia el switch del tipo de caso), cuando el Reintentar de GaranCheck
    // sale bien (recalcular) y desde la pestaña. Nunca tumba a quien la llama: va en un savepoint y el error queda en el log.
    public function calcular(int $casoId, bool $iniciar = false, bool $recalcular = false, ?Request $request = null): array
    {
        $log = new Funciones();

        try {
            $payload = json_encode([
                'caso_id' => $casoId,
                'iniciar' => $iniciar,
                'recalcular' => $recalcular,
                'auditoria' => $this->contextoAuditoriaForense($request ?? request()),
            ], JSON_UNESCAPED_UNICODE);

            $fila = DB::transaction(fn () => DB::selectOne('SELECT ventas.fn_precalifica_calcular(?::jsonb) AS resultado', [$payload]));
            $resultado = json_decode($fila->resultado, true) ?: [];

            if (in_array($resultado['estado'] ?? '', ['ERROR', 'SIN_FILAS'], true)) {
                $log->logError(PrecalificaController::class, 'No se pudo precalificar el caso #' . $casoId . ': ' . ($resultado['mensaje'] ?? $resultado['estado']));
            }

            return $resultado;
        } catch (Throwable $e) {
            $log->logError(PrecalificaController::class, 'Error al precalificar el caso #' . $casoId, $e);

            return ['estado' => 'ERROR'];
        }
    }

    // Guardar el TIPO CRÉDITO del caso (requerimiento con la marca TIPO_CREDITO): elige la precalifica de esa línea, llena los 6
    // requerimientos marcados y los bloquea. Devuelve null si salió bien, o el mensaje para el usuario.
    public function elegir(int $requerimientoCasoId, ?string $linea, Request $request): ?string
    {
        $payload = json_encode([
            'requerimiento_caso_id' => $requerimientoCasoId,
            'linea' => $linea,
            'auditoria' => $this->contextoAuditoriaForense($request),
        ], JSON_UNESCAPED_UNICODE);

        try {
            DB::transaction(fn () => DB::selectOne('SELECT ventas.fn_precalifica_elegir(?::jsonb) AS resultado', [$payload]));

            return null;
        } catch (Throwable $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                    return str_replace(':linea', trim((string) $linea), $mensaje);
                }
            }

            (new Funciones())->logError(PrecalificaController::class, 'Error al elegir el tipo de crédito del requerimiento #' . $requerimientoCasoId, $e);

            return 'No se pudo cargar la precalifica en los requerimientos del caso.';
        }
    }

    private function datosPestana(int $casoId): ?array
    {
        $fila = DB::selectOne('SELECT ventas.fn_precalifica_listar(?) AS datos', [$casoId]);

        return $fila && $fila->datos !== null ? json_decode($fila->datos, true) : null;
    }
}
