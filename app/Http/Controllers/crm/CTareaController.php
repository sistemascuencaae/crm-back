<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CTipoTarea;
use App\Models\crm\DTipoTarea;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery\Undefined;

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

    public function updateCTarea(Request $request, $id)
    {


        try {
            $tareas = $request->input('tareas');
            $cTarea = $request->all();
            // $cTarea = CTipoTarea::findOrFail($id);
            // echo (json_encode($tareas));

            $dataRe = DB::transaction(function () use ($cTarea, $id, $tareas) {
                CTipoTarea::where('id', $id)
                    ->update([
                        'nombre' => $cTarea['nombre'],
                        'estado' => $cTarea['estado']
                    ]);

                    //echo('guardado: '.json_encode($tareas));

                for ($i = 0; $i < sizeof($tareas); $i++) {


                    if($tareas[$i]['id']>0){
                        DTipoTarea::where('id', $tareas[$i]['id'])
                        ->update($tareas[$i]);
                    }else{
                        $d = DTipoTarea::create([
                            "ctt_id" => $id,
                            "nombre" => $tareas[$i]['nombre'],
                            "requerido" => $tareas[$i]['requerido'],
                            "estado" => $tareas[$i]['estado']
                        ]); 
                    }


                    // // $dTipoTarea = DTipoTarea::where('ctt_id', $id)->where('ctt_id', $tareas[$i])->first();
                    // //echo($tareas[$i]['id']);
                    // $dt = DTipoTarea::find($tareas[$i]['id']);
                    // if ($dt) {
                    //     //DB::update('UPDATE crm.dtipo_tarea (id) values (?)', [$tareas[$i]['ctt_id'], $id]);
                    //     $dt->updated([$tareas[$i]]);
                    // }
                    //echo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'.$dt);
                }

                // echo (json_encode($cTarea));
               // return CTipoTarea::with('dTipoTarea')->where('id', $id)->get();
            });

            //return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo la Tarea con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
        // UPDATE crm.dtipo_tarea
        // SET ctt_id=80, nombre='ULLOA VICENTE', requerido=true, estado=true, created_at='2023-07-10 10:15:51.000', updated_at='2023-07-10 10:15:51.000', deleted_at=NULL
        // WHERE id=23;
    }

}