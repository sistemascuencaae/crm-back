<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use Exception;
use Illuminate\Http\Request;

class CasoController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function add(Request $request){
        try {
            $result = Caso::create($request->all());
            $data = Caso::with('user', 'entidad')->where('id',$result['id'])->first();
            return response()->json(RespuestaApi::returnResultado('success', 'El listado de clientes', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Exception', $th->getMessage()));
        }
    }

    public function list()
    {
        $data = Caso::with('caso.user', 'caso.entidad')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
    }
    public function edit(Request $request)
    {
        try{
            $caso = Caso::find($request->input('id'));
            $caso->update($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'El caso se actualizo con exito', $caso));

        }catch(Exception $e){
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()));
        }
    }
}
