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

    public function editTareas(Request $request, $id)
    {
        try {
            $eliminados = $request->input('eliminados');
            $tareas = $request->input('tareas');
            $ctarea = $request->all();

            //echo(json_encode($eliminados[0]['id']));
            DB::transaction(function () use ($ctarea, $id, $eliminados, $tareas) {
                // CTipoTarea::where('id', $id)
                //     ->update([
                //         // 'ctt_id' => $ctarea['ctt_id'],
                //         'nombre' => $ctarea['nombre'],
                //         // 'requerido' => $ctarea['requerido'],
                //         'estado' => $ctarea['estado'],
                //     ]);

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.tareas WHERE caso_id = " . $id . " and id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($tareas); $i++) {
                    $tabl = Tareas::where('caso_id', $id)->where('id', $tareas[$i])->first();
                    if (!$tabl) {
                        // id
                        // caso_id
                        // nombre
                        // requerido
                        // estado
                        // created_at
                        // updated_at
                        // deleted_at
                        // ctt_id
                        // marcado
                        Tareas::create([
                            "caso_id" => $id,
                            "nombre" => $tareas[$i]['nombre'],
                            "requerido" => $tareas[$i]['requerido'],
                            "estado" => $tareas[$i]['estado'],
                            "ctt_id" => $tareas[$i]['ctt_id'],
                            "marcado" => $tareas[$i]['marcado']
                        ]);
                    }
                }
                echo (json_encode($ctarea));
                return $ctarea;
            });

            // $dataRe = CTipoTarea::with('dTipoTarea')->where('id', $id)->first();

            // return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo el tablero con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}

// public function add(Request $request){

//     //  echo('aaaa'.json_encode($request->all()));

//     try {
//         $tarea = Tarea::create($request->all());
//         return response()->json(RespuestaApi::returnResultado('success', 'Tarea creada con exito', $tarea));
//     } catch (Exception $e) {
//         return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e));
//     }
// }


// public function list(Request $request){
//     $data = Tarea::with('Etiqueta', 'Galeria', 'Archivo','User')->get();
//     return response()->json(RespuestaApi::returnResultado('success', 'Lista de tareas', $data));

// }

// public function actualizarTareas(Request $request)
// {
//     $listaIds = $request->input('listaIds');
//     $flujo_id = $request->input('flujo_id');
//     try {
//         for ($i = 0; $i < sizeof($listaIds); $i++) {
//             DB::update('update tarea set orden = ' . ($i + 1) . ', flujo_id = ' . $flujo_id . ' where id = ' . $listaIds[$i]);
//         }
//         $data = Flujo::with('tarea')->get();
//         broadcast(new CRMEvents($data));
//         return response()->json(RespuestaApi::returnResultado('success', 'Tareas actualizadas', $data));
//     } catch (Exception $e) {
//         return response()->json(RespuestaApi::returnResultado('exception', 'Error interno',$e->getMessage()));
//     }
// }



// public function buscarTarea($id){

//     $tarea = Tarea::find($id);
//     return response()->json(RespuestaApi::returnResultado('success', 'Tarea encontrada', $tarea));
// }

// public function actualizarTarea(Request $request)
// {
//     try {
//         $tarea = Tarea::findOrFail($request->input('id'));
//         $tarea->update($request->all());
//         return response()->json(RespuestaApi::returnResultado('success', 'Tarea actualizada', $tarea));
//     } catch (Exception $e) {
//         return response()->json(RespuestaApi::returnResultado('error', 'Error interno', $e));
//     }
// }