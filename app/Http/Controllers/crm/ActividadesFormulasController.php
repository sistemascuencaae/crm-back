<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ActividadesFormulas;
use Exception;
use Illuminate\Http\Request;

class ActividadesFormulasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listActividadesFormulasByTablero($id)
    {
        $log = new Funciones();
        try {
            $respuestas = ActividadesFormulas::where('tab_id', $id)->with('estado_actual', 'respuesta_actividad', 'estado_proximo')->get();
            $log->logInfo(ActividadesFormulasController::class, 'Se listo con exito las formulas de las actividades por tab_id: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(ActividadesFormulasController::class, 'Error al listar las formulas de las actividades por tab_id: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addActividadesFormulas(Request $request)
    {
        $log = new Funciones();
        try {
            // Validar si ya existe un registro con el mismo result_id_actual y result_id
            $existingRecord = ActividadesFormulas::where('result_id_actual', $request->result_id_actual)
                ->where('result_id', $request->result_id)
                ->with('estado_actual', 'respuesta_actividad', 'estado_proximo')
                ->first();

            if ($existingRecord) {
                // Si ya existe un registro con los mismos valores, devuelve un error
                $log->logInfo(ActividadesFormulasController::class, 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_actividad->nombre);
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_actividad->nombre, ''));

            } else {

                // Si no existe un registro con los mismos valores, crea el nuevo registro
                $respuestas = ActividadesFormulas::create($request->all());

                $resultado = ActividadesFormulas::where('tab_id', $respuestas->tab_id)
                    ->with('estado_actual', 'respuesta_actividad', 'estado_proximo')
                    ->orderBy('id', 'DESC')
                    ->get();

                $log->logInfo(ActividadesFormulasController::class, 'Se guardo con exito la formula para una actividad');

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
            }

        } catch (Exception $e) {
            $log->logError(ActividadesFormulasController::class, 'Error al guardar la formula para una actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editActividadesFormulas(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $respuestas = ActividadesFormulas::findOrFail($id);

            // Validar si la actualización resultaría en valores duplicados
            $existingRecord = ActividadesFormulas::where('result_id_actual', $request->result_id_actual)
                ->where('result_id', $request->result_id)
                ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                ->first();

            if ($existingRecord) {
                $log->logError(ActividadesFormulasController::class, 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_actividad->nombre);
                // Si la actualización resultaría en valores duplicados, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_actividad->nombre, ''));

            } else {

                $respuestas->update($request->all());

                $resultado = ActividadesFormulas::where('id', $respuestas->id)
                    ->with('estado_actual', 'respuesta_actividad', 'estado_proximo')
                    ->first();

                $log->logInfo(ActividadesFormulasController::class, 'Se actualizo con exito la formula de la actividad, con el ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
            }

        } catch (Exception $e) {
            $log->logError(ActividadesFormulasController::class, 'Error al actualizar la formula de la actividad, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function deleteActividadesFormulas($id)
    {
        $log = new Funciones();
        try {
            $respuestas = ActividadesFormulas::findOrFail($id);

            $respuestas->delete();
            $log->logInfo(ActividadesFormulasController::class, 'Se elimino con exito la formula de la actividad, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(ActividadesFormulasController::class, 'Error al eliminar la formula de la actividad, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    //Consultar pora traer la formula
    public function listActividadFormulaById($result_id_actual, $result_id)
    {
        $log = new Funciones();
        try {
            $respuesta = ActividadesFormulas::where('result_id_actual', $result_id_actual)->where('result_id', $result_id)->with('estado_actual', 'respuesta_actividad', 'estado_proximo')->first();

            if ($respuesta) {
                $log->logInfo(ActividadesFormulasController::class, 'Se listo con exito la formula de la actividad por result_id_actual: ' . $result_id_actual . ' y result_id: ' . $result_id);

                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
            } else {
                $log->logError(ActividadesFormulasController::class, 'No existe una fórmula con el estado Actual de la actividad');

                return response()->json(RespuestaApi::returnResultado('error', 'No existe una fórmula con el estado Actual de la actividad', ''));
            }
        } catch (Exception $e) {
            $log->logError(ActividadesFormulasController::class, 'Error al listar la formula de la actividad por result_id_actual: ' . $result_id_actual . ' y result_id: ' . $result_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}