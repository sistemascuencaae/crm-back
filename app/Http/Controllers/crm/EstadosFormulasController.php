<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\EstadosFormulas;
use Exception;
use Illuminate\Http\Request;

class EstadosFormulasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listEstadosFormulasByTablero($id)
    {
        try {
            $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addEstadosFormulas(Request $request)
    {
        try {
            // Validar si ya existe un registro con el mismo est_id_actual y resp_id
            $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                ->where('resp_id', $request->resp_id)
                ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
                ->first();

            if ($existingRecord) {
                // Si ya existe un registro con los mismos valores, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre, ''));

            } else {

                // Si no existe un registro con los mismos valores, crea el nuevo registro
                $respuestas = EstadosFormulas::create($request->all());

                $resultado = EstadosFormulas::where('tab_id', $respuestas->tab_id)
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
                    ->orderBy('id', 'DESC')
                    ->get();

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEstadosFormulas(Request $request, $id)
    {
        try {
            $respuestas = EstadosFormulas::findOrFail($id);

            // Validar si la actualización resultaría en valores duplicados
            $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                ->where('resp_id', $request->resp_id)
                ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                ->first();

            if ($existingRecord) {
                // Si la actualización resultaría en valores duplicados, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre, ''));

            } else {

                $respuestas->update($request->all());

                $resultado = EstadosFormulas::where('id', $respuestas->id)
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
                    ->first();

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteEstadosFormulas(Request $request, $id)
    {
        try {
            $respuestas = EstadosFormulas::findOrFail($id);

            $respuestas->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}