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

    public function list($tableroId)
    {
        $data = Fase::with('caso.user','caso.entidad', 'caso.resumen', 'caso.tareas','caso.actividad','caso.miembros')->where('tab_id',$tableroId)->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
    }

    public function edit(Request $request)
    {
        //$data = Fase::with('caso.user','caso.entidad', 'caso.resumen', 'caso.tareas','caso.actividad')->where('tab_id',$tableroId)->get();
        try {
        $idFase = $request->input('id');
        $data = Fase::find($idFase);
        $data->update([
            "nombre" => $request->input('nombre'),
            "descripcion" => $request->input('descripcion'),
            "estado" => $request->input('estado'),
            "orden" => $request->input('orden'),
            "generar_caso" => $request->input('generar_caso'),
            "color_id" => $request->input('color_id'),
        ]);
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error al actualizar fase.', $th->getMessage()));
        }
    }
    public function add(Request $request)
    {
        try {
            $faseCreada = Fase::create($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'Fase creada con exito', $faseCreada));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error al crear fase', $e));
        }
    }
}
