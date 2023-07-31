<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Fase;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function list($tableroId)
    {



        try {
            $user = auth('api')->user();
            $data = Fase::with('caso.user','caso.entidad', 'caso.resumen', 'caso.tareas','caso.actividad','caso.miembros.usuario','caso.Etiqueta')->where('tab_id',$tableroId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigiocon exito', $data));
            } catch (\Throwable $th) {
                return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $th->getMessage()));
            }






        // $user = auth('api')->user();
        // $id = 0;
        // $id = $user->id;
        // $fases = Fase::with('caso.user','caso.entidad', 'caso.resumen', 'caso.tareas','caso.actividad','caso.miembros.usuario','caso.Etiqueta')->where('tab_id',$tableroId)->get();


        // $arrayFiltrado = array();
        // for($i = 0; $i < sizeof($fases); $i++){
        //     // $fase = $fases[$i]['caso'];
        //     // array_push($fase,);
        //     $fases[$i]['caso'] = $fases[$i]['caso']->filter(function ($caso) {
        //         $user = auth('api')->user();
        //         $id = $user->id;
        //         return $caso['user_id'] == $id;
        //     });
        //     array_push($arrayFiltrado,$fases[$i]);


        //     echo('comparacinL '.json_encode($fases[$i]['caso']));
            // $a = array_filter($fases[$i]['caso'], function($caso) {
            //     $user = auth('api')->user();
            //     $id = $user->id;
            //     return $caso->id == $id;
            // });
            //echo('fcaxs casos: '.json_encode($a));
            // array_push($arrayFiltrado,);

        //}











        //return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigiocon exito', $arrayFiltrado));
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
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigiocon exito', $data));
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
