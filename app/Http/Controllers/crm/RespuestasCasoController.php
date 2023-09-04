<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\RespuestasCaso;
use Exception;
use Illuminate\Http\Request;

class RespuestasCasoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listRespuestasCasoByTablero($id)
    {
        try {
            // $respuestas = RespuestasCaso::where('tab_id', $id)->with('tipo_estado')->get();
            $respuestas = RespuestasCaso::where('tab_id', $id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listRespuestasCasoActivoByTablero($id)
    {
        try {
            // $respuestas = RespuestasCaso::where('tab_id', $id)->with('tipo_estado')->get();
            $respuestas = RespuestasCaso::where('tab_id', $id)->where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addRespuestasCaso(Request $request)
    {
        try {
            $respuestas = RespuestasCaso::create($request->all());

            // $resultado = Estados::where('tab_id', $respuestas->tab_id)->with('tipo_estado')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            $resultado = RespuestasCaso::where('tab_id', $respuestas->tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editRespuestasCaso(Request $request, $id)
    {
        try {
            $respuestas = RespuestasCaso::findOrFail($id);

            $respuestas->update($request->all());

            // $resultado = Estados::where('id', $respuestas->id)->with('tipo_estado')->first();
            $resultado = RespuestasCaso::where('id', $respuestas->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteRespuestasCaso(Request $request, $id)
    {
        try {
            $respuestas = RespuestasCaso::findOrFail($id);

            $respuestas->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}