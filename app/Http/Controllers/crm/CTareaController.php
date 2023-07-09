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
    public function addCTarea(Request $request)
    {
        // try {
        //     $tab = $request->all();
        //     DB::transaction(function () use ($tab) {
        //         $tablero = Tablero::create($tab);
        //         for ($i = 0; $i < sizeof($tab['usuarios']); $i++) {
        //             DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$tab['usuarios'][$i]['id'], $tablero['id']]);
        //         }
        //     });

        //     $dataRe = Tablero::with('tableroUsuario.usuario')->orderBy("id", "desc")->get();

        //     return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el tablero con éxito', $dataRe));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
        try {

            // $tareas = $request->input('tareas');
            $cTar = $request->all();

            DB::transaction(function () use ($cTar) {
                $cTarea = CTipoTarea::create($cTar);

                for ($i = 0; $i < sizeof($cTar['tareas']); $i++) {
                    DB::insert('INSERT INTO crm.dtipo_tarea (id, ctti_id) values (?, ?)', [$cTar['tareas'][$i]['id'], $cTarea['id']]);
                }
            });

            $dataRe = CTipoTarea::with('CTipoTarea.d_tipo_tarea')->orderBy("id", "desc")->get();
            echo ($dataRe);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la Tarea con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function listTareasByIdTablero($tab_id)
    {
        try {
            $tareas = CTipoTarea::where('tab_id', $tab_id)->with('DTipoTarea')->orderBy('estado', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo las tareas del tablero con éxito', $tareas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}