<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
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
        try {
            $respuestas = ActividadesFormulas::where('tab_id', $id)->with('estado_actual', 'respuesta_actividad', 'estado_proximo')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addActividadesFormulas(Request $request)
    {
        try {
            // Validar si ya existe un registro con el mismo result_id_actual y result_id
            $existingRecord = ActividadesFormulas::where('result_id_actual', $request->result_id_actual)
                ->where('result_id', $request->result_id)
                ->with('estado_actual', 'respuesta_actividad', 'estado_proximo')
                ->first();

            if ($existingRecord) {
                // Si ya existe un registro con los mismos valores, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_actividad->nombre, ''));

            } else {

                // Si no existe un registro con los mismos valores, crea el nuevo registro
                $respuestas = ActividadesFormulas::create($request->all());

                $resultado = ActividadesFormulas::where('tab_id', $respuestas->tab_id)
                    ->with('estado_actual', 'respuesta_actividad', 'estado_proximo')
                    ->orderBy('id', 'DESC')
                    ->get();

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editActividadesFormulas(Request $request, $id)
    {
        try {
            $respuestas = ActividadesFormulas::findOrFail($id);

            // Validar si la actualización resultaría en valores duplicados
            $existingRecord = ActividadesFormulas::where('result_id_actual', $request->result_id_actual)
                ->where('result_id', $request->result_id)
                ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                ->first();

            if ($existingRecord) {
                // Si la actualización resultaría en valores duplicados, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_actividad->nombre, ''));

            } else {

                $respuestas->update($request->all());

                $resultado = ActividadesFormulas::where('id', $respuestas->id)
                    ->with('estado_actual', 'respuesta_actividad', 'estado_proximo')
                    ->first();

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function deleteActividadesFormulas(Request $request, $id)
    {
        try {
            $respuestas = ActividadesFormulas::findOrFail($id);

            $respuestas->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    //Consultar pora traer la formula
    public function listActividadFormulaById($result_id_actual, $result_id)
    {
        try {
            $respuesta = ActividadesFormulas::where('result_id_actual', $result_id_actual)->where('result_id', $result_id)->with('estado_actual', 'respuesta_actividad', 'estado_proximo')->first();

            if ($respuesta) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe una fórmula con el estado Actual de la actividad', ''));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}