<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Fase;
use Exception;
use Illuminate\Http\Request;

class FaseController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function list()
    {
        $data = Fase::with('caso.user','caso.entidad', 'caso.tareas','caso.actividad')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
    }
    public function add(Request $request)
    {
        try {
            $faseCreada = Fase::create($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'Fase creada con exito', $faseCreada));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e));
        }
    }
}
