<?php

namespace App\Http\Controllers;

use App\Http\Resources\RespuestaApi;
use App\Models\Flujo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlujoController extends Controller
{    
    
    
    public function __construct()
    {
        $this->middleware('auth:api');
    }




    public function actualizarFlujo(request $request) {

        

        DB::transaction(function() use ($request) {
            $flujo_id = $request->input('flujo_id');
            $posision = $request->input('posision');















            //DB::rollBack();
            //DB::commit();
        });






        
        
        
        
        
        
        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success','El listado de flujos se consigion con exito',$data));
    }



    public function listarFlujos() {
        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success','El listado de flujos se consigion con exito',$data));
    }


}
