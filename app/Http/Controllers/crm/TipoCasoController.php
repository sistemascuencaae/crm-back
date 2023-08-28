<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoCaso;
use Exception;
use Illuminate\Http\Request;

class TipoCasoController extends Controller
{
    public function addTipoCaso(Request $request)
    {
        try {
            $tipoCaso = TipoCaso::create($request->all());

            $resultado = TipoCaso::where('tab_id', $tipoCaso->tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTipoCasoByIdTablero($tab_id)
    {
        try {
            $resultado = TipoCaso::where('tab_id', $tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listByIdTipoCasoActivo($tc_id)
    {
        try {
            $resultado = TipoCaso::where('id', $tc_id)->where('estado', true)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTipoCasoByIdTableroEstadoActivo($tab_id)
    {
        try {
            $resultado = TipoCaso::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTipoCaso(Request $request, $id)
    {
        try {
            $resultado = TipoCaso::findOrFail($id);

            $resultado->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteTipoCaso($id)
    {
        try {
            $resultado = TipoCaso::findOrFail($id);

            $resultado->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}