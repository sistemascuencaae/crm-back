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

    public function list(Request $request)
    {


        $tabId = $request->input('tabId');
        $userId = $request->input('userId');
        $usuTipo = $request->input('usuTipo');
        //echo json_encode($tabId);
        try {
        $user = auth('api')->user();
        $data = Fase::with([
            'caso.user',
            'caso.userCreador',
            'caso.entidad',
            'caso.resumen',
            'caso.tareas' => function ($query) use ($tabId) { $query->where('tab_id', $tabId); },
            'caso.actividad',
            'caso.miembros.usuario.departamento',
            'caso.Etiqueta',
            'caso.requerimientosCaso'
        ])->where('tab_id',$tabId)->get();



            return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigiocon exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $th->getMessage()));
        }
    }

    // public function filtrarTareasTablero(Request $request)
    // {
    //     $jsonData = $request->input('jsonData');
    //     $decodedData = json_decode($jsonData);

    //     $filteredArray = array_map(function ($innerArray) {
    //         return array_filter($innerArray, function ($value) {
    //             return $value % 2 == 0; // Filtrar números pares en el array interno
    //         });
    //     }, $decodedData->data);

    //     return response()->json(['filteredArray' => $filteredArray]);
    // }

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
