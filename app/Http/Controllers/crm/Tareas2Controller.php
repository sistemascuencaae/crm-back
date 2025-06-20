<?php

namespace App\Http\Controllers\crm;

use App\Models\crm\TipoCasoCTipoTarea;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\crm\Tareas2;
use Exception;

class Tareas2Controller extends Controller
{

    // ! metodo que lista las tareas del tipo de caso e inserta en la tabla crm.tareas2 las tareas nuevas
    public function listTareasByTipoCasoId($tipo_caso_id, $caso_id)
    {
        try {
            $data = DB::transaction(function () use ($tipo_caso_id, $caso_id) {

                $tareas = TipoCasoCTipoTarea::where('tipo_caso_id', $tipo_caso_id)
                    ->with(['cTipoTarea.dTipoTarea' => function ($query) {
                        $query->orderBy('id', 'asc');
                    }])
                    ->first();

                if ($tareas && $tareas->cTipoTarea && $tareas->cTipoTarea->dTipoTarea) {
                    foreach ($tareas->cTipoTarea->dTipoTarea as $tarea) {

                        // Verificamos si ya existe en tareas2 con la llave compuesta
                        $existe = Tareas2::where('dtt_id', $tarea->id)
                            ->where('caso_id', $caso_id)
                            ->exists();

                        if (!$existe) {
                            // Insertamos la tarea en tareas2
                            Tareas2::create([
                                "caso_id"   => $caso_id,
                                "ctt_id"    => $tarea->ctt_id,
                                "dtt_id"    => $tarea->id,
                                "nombre"    => $tarea->nombre,
                                "requerido" => $tarea->requerido,
                                "marcado"   => $tarea->marcado,
                            ]);
                        }
                    }
                }

                return Tareas2::where('caso_id', $caso_id)->orderBy('id', 'asc')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    //! metodo que actualiza la tarea en la tabla crm.tareas2
    public function editTareas2(Request $request, $tarea_id) 
{
    try {
        $data = DB::transaction(function () use ($request, $tarea_id) {
            // Buscar y actualizar en una sola instrucción
            Tareas2::where('dtt_id', $tarea_id)
                ->update([
                    "marcado" => $request->marcado,
                ]);

            return Tareas2::where('dtt_id', $tarea_id)->first();
        });

        return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
    } catch (Exception $e) {
        return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
    }
}


}


