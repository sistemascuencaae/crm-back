<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\EstadosFormulas;
use App\Models\configuracion\TipoCasoTablero;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadosFormulasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listEstadosFormulasByTablero($id)
    {
        $log = new Funciones();
        try {
            $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')->get();

            $log->logInfo(EstadosFormulasController::class, 'Se listo con exito los estados del tablero con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los estados del tablero con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listEstadosFormulasByTabIdByTipoCasoId($tab_id, $tipo_caso_id)
    {
        $log = new Funciones();
        try {
            // original
            // $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima','tipoCaso')->get();

            // nuevo cambio
            $respuestas = TipoCasoTablero::where('tipo_caso_id', $tipo_caso_id)->where('tab_id', $tab_id)
                ->with(
                    'estado_formula.estado_actual',
                    'estado_formula.fase_actual',
                    'estado_formula.respuesta_caso',
                    'estado_formula.estado_proximo',
                    'estado_formula.tablero_proximo',
                    'estado_formula.fase_proxima',
                    'estado_formula.tipoCaso'
                )
                ->get();

            $log->logInfo(EstadosFormulasController::class, 'Se listo con exito los estados del tablero con el ID: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los estados del tablero con el ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // 27-oct: flujo de un tipo de caso para Demos > Flujos por tipo de caso.
    // 27-oct (rediseño): todo el diagrama en un JSON (crm.fn_tipo_caso_flujo): tipo_caso, tableros, fases (con la de
    // creación y su estado inicial), formulas y requerimientos.
    public function listEstadosFormulasFlujoByTipoCasoId($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_flujo(?) AS flujo', [(int) $tipo_caso_id]);
            $flujo = $fila && $fila->flujo ? json_decode($fila->flujo) : null;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $flujo));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar el flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar el flujo del tipo de caso', $e->getMessage()));
        }
    }

    // 28-oct: editor del flujo (Demos > Flujos por tipo de caso). Los combos de una fórmula: por tablero del tipo, sus
    // fases, estados y respuestas activas (crm.fn_tipo_caso_flujo_catalogos).
    public function listFlujoCatalogosByTipoCasoId($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_flujo_catalogos(?) AS catalogos', [(int) $tipo_caso_id]);
            $catalogos = $fila && $fila->catalogos ? json_decode($fila->catalogos) : null;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $catalogos));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los catálogos del flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar los catálogos del flujo', $e->getMessage()));
        }
    }

    // 28-oct: botón Guardar del editor del flujo: todos los cambios juntos. Las reglas y los mensajes están en
    // crm.fn_tipo_caso_flujo_guardar. data = {estado: success | confirmar | error, errores, avisos, version, recargar}.
    // "confirmar" llega con status success: no se guardó nada y la pantalla pregunta antes de mandar confirmar = true.
    public function guardarFlujoTipoCaso(Request $request, $tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $cambios = $request->input('cambios');
            $fila = DB::selectOne(
                'SELECT crm.fn_tipo_caso_flujo_guardar(?, ?, ?::json, ?::boolean, ?) AS resultado',
                [
                    (int) $tipo_caso_id,
                    (string) $request->input('version', ''),
                    json_encode(is_array($cambios) ? $cambios : new \stdClass(), JSON_UNESCAPED_UNICODE),
                    $request->boolean('confirmar') ? 'true' : 'false',
                    auth('api')->user()->id ?? null,
                ]
            );
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;
            $estado = $resultado->status ?? 'error';
            $mensaje = $resultado->mensaje ?? 'No se pudo guardar el flujo.';
            $data = [
                'estado' => $estado,
                'errores' => $resultado->errores ?? [],
                'avisos' => $resultado->avisos ?? [],
                'version' => $resultado->version ?? null,
                'recargar' => $resultado->recargar ?? false,
            ];

            if ($estado === 'error') {
                $log->logError(EstadosFormulasController::class, 'No se guardó el flujo del tipo de caso con el ID: ' . $tipo_caso_id . ': ' . $mensaje);

                return response()->json(RespuestaApi::returnResultado('error', $mensaje, $data));
            }

            if ($estado === 'success') {
                $log->logInfo(EstadosFormulasController::class, 'Se guardó el flujo del tipo de caso con el ID: ' . $tipo_caso_id . ': ' . $mensaje);
            }

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $data));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al guardar el flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar el flujo.', $e->getMessage()));
        }
    }

    // 28-oct: clona el tipo de caso con todo su flujo (crm.fn_tipo_caso_clonar): sale activo y con el nombre + "_clon".
    // data = {id, nombre} del tipo nuevo.
    public function clonarTipoCaso($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_clonar(?) AS resultado', [(int) $tipo_caso_id]);
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;

            if (($resultado->status ?? 'error') !== 'success') {
                $mensaje = $resultado->mensaje ?? 'No se pudo clonar el tipo de caso.';
                $log->logError(EstadosFormulasController::class, 'No se clonó el tipo de caso con el ID: ' . $tipo_caso_id . ': ' . $mensaje);

                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }

            $log->logInfo(EstadosFormulasController::class, 'Se clonó el tipo de caso con el ID: ' . $tipo_caso_id . ' en el ID: ' . $resultado->id);

            return response()->json(RespuestaApi::returnResultado('success', $resultado->mensaje, ['id' => $resultado->id, 'nombre' => $resultado->nombre]));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al clonar el tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo clonar el tipo de caso.', $e->getMessage()));
        }
    }

    public function listarTiposCasoTablero($tabId)
    {
        try {

            $data = DB::select("SELECT * FROM crm.tipo_caso WHERE tab_id = ?", [$tabId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function addEstadosFormulas(Request $request)
    {
        $log = new Funciones();
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, &$error, &$exitoso) {
                // Validar si ya existe un registro con el mismo est_id_actual y resp_id
                $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                    ->where('resp_id', $request->resp_id)
                    ->where('tipo_caso_id', $request->tipo_caso_id)
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')
                    ->first();

                if ($existingRecord) {
                    // Si ya existe un registro con los mismos valores, devuelve un error
                    $error = 'Ya EXISTE un registro con los valores tipo caso: ' . $existingRecord->tipoCaso->nombre . ', estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    return null;
                } else {

                    // Si no existe un registro con los mismos valores, crea el nuevo registro
                    $respuestas = EstadosFormulas::create($request->all());

                    $resultado = EstadosFormulas::where('tab_id', $respuestas->tab_id)
                        ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')
                        ->orderBy('id', 'DESC')
                        ->get();

                    $exitoso = $resultado;
                    return null;
                }
            });

            if ($error) {
                $log->logError(EstadosFormulasController::class, $error);

                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                $log->logInfo(EstadosFormulasController::class, 'Se guardo con exito la formula del estado');

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al guardar en addEstadosFormulas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEstadosFormulas(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, $id, &$error, &$exitoso) {
                $respuestas = EstadosFormulas::findOrFail($id);

                // Validar si la actualización resultaría en valores duplicados
                $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                    ->where('resp_id', $request->resp_id)
                    ->where('tipo_caso_id', $request->tipo_caso_id)
                    ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                    ->first();

                if ($existingRecord) {
                    // Si la actualización resultaría en valores duplicados, devuelve un error
                    // $error = 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    $error = 'Ya EXISTE un registro con los valores tipo caso: ' . $existingRecord->tipoCaso->nombre . ', estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    return null;
                } else {

                    $respuestas->update($request->all());

                    $resultado = EstadosFormulas::where('id', $respuestas->id)
                        ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')
                        ->first();

                    $exitoso = $resultado;
                    return null;
                }
            });

            if ($error) {
                $log->logError(EstadosFormulasController::class, $error);

                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                $log->logInfo(EstadosFormulasController::class, 'Se actualizo con exito la formula del estado con el estado ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $exitoso));
            }
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al actualizar la formula del estado con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteEstadosFormulas($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id, &$error, &$exitoso) {
                $respuesta = EstadosFormulas::findOrFail($id);

                $respuesta->delete();

                return $respuesta;
            });

            $log->logInfo(EstadosFormulasController::class, 'Se elimino con exito la formula del estado con el estado ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al eliminar la formula del estado con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }



    //! START PARA LA PANTALLA DE MOVER CASOS MASIVAMENTE
    public function listRespuestasCaso($tab_id, $tipo_caso_id, $fas_id, $estado_id)
    {
        try {
            $respuestas = EstadosFormulas::where('tab_id', $tab_id)
                ->where('tipo_caso_id', $tipo_caso_id)
                ->where('est_id_actual', $estado_id)
                ->where('fase_id_actual', $fas_id)
                ->whereHas('respuesta_caso', function ($query) use ($fas_id) {
                    $query->where('fase_id', $fas_id);
                })
                ->with([
                    'estado_actual',
                    'fase_actual',
                    'respuesta_caso',
                    'estado_proximo',
                    'tablero_proximo',
                    'fase_proxima',
                    'tipoCaso'
                ])
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    //! END PARA LA PANTALLA DE MOVER CASOS MASIVAMENTE

}
