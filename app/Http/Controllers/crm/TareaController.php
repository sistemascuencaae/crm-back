<?php

namespace App\Http\Controllers\crm;

use App\Events\CRMEvents;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Flujo;
use App\Models\Tarea;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TareaController extends Controller
{
    

    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function actualizarTareas(Request $request)
    {
        $listaIds = $request->input('listaIds');
        $flujo_id = $request->input('flujo_id');
        try {
            for ($i = 0; $i < sizeof($listaIds); $i++) {
                DB::update('update tarea set orden = ' . ($i + 1) . ', flujo_id = ' . $flujo_id . ' where id = ' . $listaIds[$i]);
            }
            $data = Flujo::with('tarea')->get();
            broadcast(new CRMEvents($data));
            return response()->json(RespuestaApi::returnResultado('success', 'Tareas actualizadas', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error interno',$e->getMessage()));
        }
    }



    public function buscarTarea($id){
        
        $tarea = Tarea::find($id);
        return response()->json(RespuestaApi::returnResultado('success', 'Tarea encontrada', $tarea));
    }

    public function actualizarTarea(Request $request)
    {
        try {
            $tarea = Tarea::findOrFail($request->input('id'));
            $tarea->update($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'Tarea actualizada', $tarea));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error interno', $e));
        }
    }
}
