<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Requerimientos;
use Exception;
use Illuminate\Http\Request;

class RequerimientoController extends Controller
{

    public function listRequerimientosCasoById($caso_id)
    {
        try {
            $requerimientos = Requerimientos::where('caso_id', $caso_id)->orderBy('marcado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimientos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addRequerimientos(Request $request)
    {
        try {
            $requerimiento = Requerimientos::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $requerimiento));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editRequerimientos(Request $request, $id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            $requerimiento->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $requerimiento));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteRequerimientos($id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            $requerimiento->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $requerimiento));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}