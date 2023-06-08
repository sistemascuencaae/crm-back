<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Flujo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlujoController extends Controller
{


    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function list()
    {
        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de flujos se consigion con exito', $data));
    }
    public function create(Request $request)
    {
        try {
            $flujoCreado = Flujo::create($request->all());
            $data = Flujo::with('tarea')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Flujo creado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e));
        }
    }
    public function update(request $request)
    {
        $id = $request->input('id');
        Flujo::where('id', '=', $id)->update($request->all());

        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'Flujo actualizado', $data));
    }

    public function delete($id)
    {
        try {
            $flujo = Flujo::find($id);
            $flujo->orden = null;
            $flujo->save();
            $flujo->delete();
            $data = Flujo::with('tarea')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Flujo eliminado', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e));
        }
    }


    public function updateFlujos(Request $request){ 
        $listaIds = $request->input('listaIds');
        try {
            for ($i = 0; $i < sizeof($listaIds); $i++) {
                if($listaIds[$i] != null){
                    DB::update('update flujo set orden = ' . ($i + 1) . ' where id = ' . $listaIds[$i]);
                }
            }
            $data = Flujo::with('tarea')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Tareas actualizadas', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error interno',$e->getMessage()));
        }
    }

}
