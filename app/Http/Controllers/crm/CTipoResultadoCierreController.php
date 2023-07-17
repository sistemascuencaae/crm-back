<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CTipoResultadoCierre;
use Exception;
use Illuminate\Http\Request;

class CTipoResultadoCierreController extends Controller
{
    public function addCTipoResultadoCierre(Request $request)
    {
        try {
            CTipoResultadoCierre::create($request->all());

            $resultado = CTipoResultadoCierre::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoResultadoCierreByIdTablero($tab_id)
    {
        try {
            $resultado = CTipoResultadoCierre::where('tab_id', $tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoResultadoCierreByIdTableroEstadoActivo($tab_id)
    {
        try {
            $resultado = CTipoResultadoCierre::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editCTipoResultadoCierre(Request $request, $id)
    {
        try {
            $resultado = CTipoResultadoCierre::findOrFail($id);

            $resultado->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteCTipoResultadoCierre($id)
    {
        try {
            $resultado = CTipoResultadoCierre::findOrFail($id);

            $resultado->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}