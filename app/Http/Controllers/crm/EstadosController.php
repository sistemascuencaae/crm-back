<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Estados;
use App\Models\crm\Galeria;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EstadosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listEstadosByTablero($id)
    {
        try {
            $estado = Estados::where('tab_id', $id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $estado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addEstado(Request $request)
    {
        try {
            $estado = Estados::create($request->all());

            $resultado = Estados::where('tab_id', $estado->tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEstado(Request $request, $id)
    {
        try {
            $estado = Estados::findOrFail($id);

            $estado->update($request->all());

            $resultado = Estados::where('id', $estado->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteEstado(Request $request, $id)
    {
        try {
            $estado = Estados::findOrFail($id);

            $estado->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $estado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}