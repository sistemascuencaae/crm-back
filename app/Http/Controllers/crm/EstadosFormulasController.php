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
            $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'respuesta_caso', 'estado_proximo', 'tablero', 'fase')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addEstadosFormulas(Request $request)
    {
        try {
            $respuestas = EstadosFormulas::create($request->all());

            // $resultado = Estados::where('tab_id', $respuestas->tab_id)->with('tipo_estado')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            $resultado = EstadosFormulas::where('tab_id', $respuestas->tab_id)->with('estado_actual', 'respuesta_caso', 'estado_proximo', 'tablero', 'fase')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEstadosFormulas(Request $request, $id)
    {
        try {
            $respuestas = EstadosFormulas::findOrFail($id);

            $respuestas->update($request->all());

            // $resultado = Estados::where('id', $respuestas->id)->with('tipo_estado')->first();
            $resultado = EstadosFormulas::where('id', $respuestas->id)->with('estado_actual', 'respuesta_caso', 'estado_proximo', 'tablero', 'fase')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
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