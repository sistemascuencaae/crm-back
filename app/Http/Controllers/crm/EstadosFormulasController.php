<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\EstadosFormulas;
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
            $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')->get();

            $log->logInfo(EstadosFormulasController::class, 'Se listo con exito los estados del tablero con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los estados del tablero con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
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
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
                    ->first();

                if ($existingRecord) {
                    // Si ya existe un registro con los mismos valores, devuelve un error
                    $error = 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    return null;

                } else {

                    // Si no existe un registro con los mismos valores, crea el nuevo registro
                    $respuestas = EstadosFormulas::create($request->all());

                    $resultado = EstadosFormulas::where('tab_id', $respuestas->tab_id)
                        ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
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
                    ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                    ->first();

                if ($existingRecord) {
                    // Si la actualización resultaría en valores duplicados, devuelve un error
                    $error = 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    return null;

                } else {

                    $respuestas->update($request->all());

                    $resultado = EstadosFormulas::where('id', $respuestas->id)
                        ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
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

}