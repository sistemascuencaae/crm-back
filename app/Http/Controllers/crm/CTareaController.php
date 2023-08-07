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

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tareas));
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
                    DTipoTarea::create([
                        "ctt_id" => $cTarea['id'],
                        "nombre" => $cTar['tareas'][$i]['nombre'],
                        "requerido" => $cTar['tareas'][$i]['requerido'],
                        "estado" => $cTar['tareas'][$i]['estado'],
                        "tab_id" => $cTar['tareas'][$i]['tab_id'],
                    ]);
                }

                // return CTipoTarea::with('dTipoTarea')->orderBy("id", "desc")->get();
                return CTipoTarea::where('tab_id', $cTarea['tab_id'])->with('dTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function updateCTarea(Request $request, $id)
    // {
    //     try {
    //         $tareas = $request->input('tareas');
    //         $cTarea = $request->all();

    //         $dataRe = DB::transaction(function () use ($cTarea, $id, $tareas) {
    //             CTipoTarea::where('id', $id)
    //                 ->update([
    //                     'nombre' => $cTarea['nombre'],
    //                     'estado' => $cTarea['estado']
    //                 ]);

    //             for ($i = 0; $i < sizeof($tareas); $i++) {
    //                 if ($tareas[$i]['id']) {
    //                     DTipoTarea::where('id', $tareas[$i]['id'])
    //                         ->update($tareas[$i]);
    //                 } else {
    //                     DTipoTarea::create([
    //                         "ctt_id" => $id,
    //                         "nombre" => $tareas[$i]['nombre'],
    //                         "requerido" => $tareas[$i]['requerido'],
    //                         "estado" => $tareas[$i]['estado']
    //                     ]);
    //                 }
    //             }

    //             // return CTipoTarea::with('dTipoTarea')->orderBy('id', 'DESC')->get();
    //             return CTipoTarea::with('dTipoTarea')->get();
    //         });

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo la Tarea con éxito', $dataRe));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
    //     }
    //     // UPDATE crm.dtipo_tarea
    //     // SET ctt_id=80, nombre='ULLOA VICENTE', requerido=true, estado=true, created_at='2023-07-10 10:15:51.000', updated_at='2023-07-10 10:15:51.000', deleted_at=NULL
    //     // WHERE id=23;
    // }

    public function updateCTarea(Request $request, $id)
    {
        try {
            $eliminados = $request->input('eliminados');
            $tareas = $request->input('tareas');
            $ctarea = $request->all();

            //echo(json_encode($eliminados[0]['id']));
            $tab = DB::transaction(function () use ($ctarea, $id, $eliminados, $tareas) {
                CTipoTarea::where('id', $id)
                    ->update([
                        // 'ctt_id' => $ctarea['ctt_id'],
                        'nombre' => $ctarea['nombre'],
                        // 'requerido' => $ctarea['requerido'],
                        'estado' => $ctarea['estado'],
                    ]);

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.dtipo_tarea WHERE ctt_id = " . $id . " and id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($tareas); $i++) {
                    $tabl = DTipoTarea::where('ctt_id', $id)->where('id', $tareas[$i])->first();
                    if (!$tabl) {
                        DTipoTarea::create([
                            "ctt_id" => $id,
                            "nombre" => $tareas[$i]['nombre'],
                            "requerido" => $tareas[$i]['requerido'],
                            "estado" => $tareas[$i]['estado'],
                            "tab_id" => $tareas[$i]['tab_id'],
                        ]);
                    }
                }

                return $ctarea;
            });

            $dataRe = CTipoTarea::with('dTipoTarea')->where('id', $id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


}