<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\crm\Audits;
use App\Models\crm\RequerimientoCaso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

// Version 1.0
// Precalifica del caso (ventas.precalifica, PLANES/COTIZADOR/produccion_precalifica.sql). Se calcula al crear el caso si su tipo
// la pide (después de la calificación) y otra vez cuando el Reintentar de GaranCheck sale bien, mientras el caso no tenga
// proforma. El TIPO CRÉDITO del caso (requerimiento con la marca TIPO_CREDITO) elige la fila y llena los 6 requerimientos del
// cotizador; se puede cambiar hasta la primera proforma. 19-nov-2026 (24-nov: cambiable hasta la proforma).
class PrecalificaController extends Controller
{
    use AuditoriaForenseTrait;

    // Códigos de ventas.fn_precalifica_elegir.
    private const MENSAJES_ERROR = [
        'TIPO_CREDITO_REQUERIDO' => 'Elija el tipo de crédito.',
        // QUITADO 24-nov (se puede cambiar hasta la primera proforma): 'TIPO_CREDITO_YA_ELEGIDO' => 'El tipo de crédito ya está elegido y no se puede cambiar.',
        'TIPO_CREDITO_CON_PROFORMA' => 'El tipo de crédito ya no se puede cambiar: el caso tiene proforma.',
        'CASO_SIN_CALIFICACION' => 'Primero hay que calificar al cliente.',
        'PRECALIFICA_NO_EXISTE' => 'Primero hay que precalificar al cliente.',
        'PRECALIFICA_SIN_TIPO_CREDITO' => 'La precalifica no tiene datos de :linea.',
    ];

    // Estados de ventas.fn_precalifica_calcular para el botón Reintentar.
    private const MENSAJES_CALCULO = [
        'OK' => 'Cliente precalificado.',
        'YA_EXISTE' => 'El caso ya tiene su precalifica.',
        // QUITADO 24-nov: 'YA_ELEGIDA' => 'El tipo de crédito ya está elegido: la precalifica no cambia.',
        'CON_PROFORMA' => 'El caso ya tiene proforma: la precalifica no cambia.',
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

    // Version 1.0
    // GET /api/crm/precalificaVistaPrevia/{tipo_caso_id}/{identificacion} — 23-nov: la precalifica por cédula en el modal del
    // cliente de Nuevo Caso (el caso aún no existe). Solo lee. Si el tipo de caso no la pide, estado NO_APLICA y no se muestra.
    public function vistaPrevia($tipoCasoId, $identificacion)
    {
        $identificacion = trim((string) $identificacion);

        try {
            $payload = json_encode(['tipo_caso_id' => (int) $tipoCasoId, 'identificacion' => $identificacion], JSON_UNESCAPED_UNICODE);
            $fila = DB::selectOne('SELECT ventas.fn_precalifica_vista_previa(?::jsonb) AS resultado', [$payload]);
            $resultado = $fila && $fila->resultado !== null ? json_decode($fila->resultado, true) : null;

            if (!is_array($resultado) || ($resultado['estado'] ?? 'ERROR') === 'ERROR') {
                (new Funciones())->logError(PrecalificaController::class, 'No se pudo obtener la vista previa de la precalifica de ' . $identificacion . ': ' . ($resultado['mensaje'] ?? 'sin respuesta'));

                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener la precalifica.', ['estado' => 'ERROR']));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Throwable $e) {
            (new Funciones())->logError(PrecalificaController::class, 'Error en la vista previa de la precalifica de ' . $identificacion, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener la precalifica.', ['estado' => 'ERROR']));
        }
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

            // 24-nov: al recalcular se vuelve a aplicar el TIPO CRÉDITO elegido: foto de antes para dejar en la bitácora lo que cambie.
            $antes = $recalcular ? $this->requerimientosMarcados($casoId) : null;
            $fila = DB::transaction(fn () => DB::selectOne('SELECT ventas.fn_precalifica_calcular(?::jsonb) AS resultado', [$payload]));
            $resultado = json_decode($fila->resultado, true) ?: [];

            if (in_array($resultado['estado'] ?? '', ['ERROR', 'SIN_FILAS'], true)) {
                $log->logError(PrecalificaController::class, 'No se pudo precalificar el caso #' . $casoId . ': ' . ($resultado['mensaje'] ?? $resultado['estado']));
            }
            if ($antes !== null && ($resultado['estado'] ?? '') === 'OK') {
                $this->registrarCambiosMarcados($casoId, $antes, $request ?? request());
            }

            return $resultado;
        } catch (Throwable $e) {
            $log->logError(PrecalificaController::class, 'Error al precalificar el caso #' . $casoId, $e);

            return ['estado' => 'ERROR'];
        }
    }

    // 24-nov: los requerimientos marcados del caso (el TIPO CRÉDITO y los 6 del cotizador), por id: la foto para la bitácora.
    public function requerimientosMarcados(int $casoId): Collection
    {
        return RequerimientoCaso::where('caso_id', $casoId)->whereNotNull('dato_cotizador')->get()->keyBy('id');
    }

    // 24-nov: lo que la bitácora muestra de un requerimiento. Si nada de esto cambió, no se registra.
    private const CAMPOS_BITACORA = ['valor', 'valor_varchar', 'valor_varchar_2', 'valor_int', 'valor_decimal', 'descripcion', 'marcado'];

    // 24-nov: un registro 'editRequerimiento' en la bitácora por cada requerimiento marcado que cambió desde la foto $antes.
    // Lo usan el guardado del TIPO CRÉDITO (ReqCasoController) y el recalcular (Reintentar de GaranCheck).
    public function registrarCambiosMarcados(int $casoId, Collection $antes, Request $request): void
    {
        foreach ($this->requerimientosMarcados($casoId) as $id => $nuevo) {
            $viejo = $antes->get($id);
            // QUITADO 24-nov (comparaba todo, también updated_at: al cambiar de línea salían registros vacíos «descripción a ,»):
            // if (!$viejo || json_encode($viejo) === json_encode($nuevo)) {
            if (!$viejo || json_encode($viejo->only(self::CAMPOS_BITACORA)) === json_encode($nuevo->only(self::CAMPOS_BITACORA))) {
                continue;
            }
            $audit = new Audits();
            $audit->old_values = json_encode($viejo);
            $audit->new_values = json_encode($nuevo);
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = RequerimientoCaso::class;
            $audit->auditable_id = $id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip();
            $audit->url = $request->fullUrl();
            $audit->user_agent = $request->header('User-Agent');
            $audit->accion = 'editRequerimiento';
            $audit->caso_id = $casoId;
            $audit->save();
        }
    }

    // Guardar el TIPO CRÉDITO del caso (requerimiento con la marca TIPO_CREDITO): elige la precalifica de esa línea y llena los 6
    // requerimientos marcados (24-nov: cada uno queda como lo configuró el tipo de caso). Devuelve null si salió bien, o el mensaje.
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
