<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tareas;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;

class TareaController extends Controller
{

    public function listTareasCasoById($caso_id)
    {
        try {
            $tareas = Tareas::where('caso_id', $caso_id)->orderBy('marcado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tareas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addTareas(Request $request)
    {
        try {
            $tarea = Tareas::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $tarea));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTareas(Request $request, $id)
    {
        try {
            $tarea = Tareas::findOrFail($id);

            $tarea->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $tarea));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteTareas($id)
    {
        try {
            $tarea = Tareas::findOrFail($id);

            $tarea->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $tarea));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

// ESTE CODIGO SI VALE (ACTUALIZA, ELMINA Y CREA UNA TAREA)
// public function editTareas(Request $request, $caso_id)
//     {
//         try {
//             $eliminados = $request->input('eliminados');
//             $tareas = $request->input('tareas');
//             $ctarea = $request->all();

//             //echo(json_encode($eliminados[0]['id']));
//             DB::transaction(function () use ($ctarea, $caso_id, $eliminados, $tareas) {

//                 for ($i = 0; $i < sizeof($tareas); $i++) {
//                     // echo(json_encode($tareas[$i]['id']));
//                     $tar = Tareas::find($tareas[$i]['id']);
//                     if ($tar) {
//                         $tar->update([
//                             'marcado' => $tareas[$i]['marcado'],
//                         ]);
//                     }
//                 }

//                 for ($i = 0; $i < sizeof($eliminados); $i++) {
//                     if ($caso_id && $eliminados[$i]['id']) {
//                         DB::delete("DELETE FROM crm.tareas WHERE caso_id = " . $caso_id . " and id = " . $eliminados[$i]['id']);
//                     }
//                 }

//                 for ($i = 0; $i < sizeof($tareas); $i++) {
//                     $tabl = Tareas::where('caso_id', $caso_id)->where('id', $tareas[$i])->first();
//                     if (!$tabl) {
//                         Tareas::create([
//                             "caso_id" => $caso_id,
//                             "nombre" => $tareas[$i]['nombre'],
//                             "requerido" => $tareas[$i]['requerido'],
//                             "estado" => $tareas[$i]['estado'],
//                             "ctt_id" => $tareas[$i]['ctt_id'],
//                             "marcado" => $tareas[$i]['marcado']
//                         ]);
//                     }
//                 }
//                 return $ctarea;
//             });

//             $dataRe = Tareas::orderBy('id', 'DESC')->get();

//             return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $dataRe));
//         } catch (Exception $e) {
//             return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
//         }
//     }