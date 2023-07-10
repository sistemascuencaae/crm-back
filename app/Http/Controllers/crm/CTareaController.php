<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CTipoTarea;
use App\Models\crm\DTipoTarea;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CTareaController extends Controller
{
    public function listTareasByIdTablero($tab_id)
    {
        try {
            $tareas = CTipoTarea::where('tab_id', $tab_id)->with('DTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo las tareas del tablero con éxito', $tareas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addCTarea(Request $request)
    {
        try {
            $cTar = $request->all();
            $data = DB::transaction(function () use ($cTar) {
                $cTarea = CTipoTarea::create($cTar);
                for ($i = 0; $i < sizeof($cTar['tareas']); $i++) {
                    $d = DTipoTarea::create([
                        "ctt_id" => $cTarea['id'],
                        "nombre" => $cTar['tareas'][$i]['nombre'],
                        "requerido" => $cTar['tareas'][$i]['requerido'],
                        "estado" => $cTar['tareas'][$i]['estado']
                    ]);
                }
                return CTipoTarea::with('dTipoTarea')->orderBy("id", "desc")->where('id', $cTarea->id)->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la Tarea con éxito', $data));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateCTarea()
    {

    }

}