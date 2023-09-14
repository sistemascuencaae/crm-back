<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\PerfilAnalistas;
use Exception;
use Illuminate\Http\Request;

class PerfilAnalistasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAllPerfilAnalistas()
    {
        try {
            $perfil = PerfilAnalistas::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $perfil));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addPerfilAnalistas(Request $request)
    {
        try {
            $perfil = PerfilAnalistas::create($request->all());

            $resultado = PerfilAnalistas::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editPerfilAnalistas(Request $request, $id)
    {
        try {
            $perfil = PerfilAnalistas::findOrFail($id);

            $perfil->update($request->all());

            $resultado = PerfilAnalistas::where('id', $perfil->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deletePerfilAnalistas(Request $request, $id)
    {
        try {
            $perfil = PerfilAnalistas::findOrFail($id);

            $perfil->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $perfil));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}