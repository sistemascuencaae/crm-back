<?php

namespace App\Http\Controllers;

use App\Http\Resources\RespuestaApi;
use App\Models\Flujo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlujoController extends Controller
{    
    
    
    public function __construct()
    {
        $this->middleware('auth:api');
    }










    public function actualizarFlujo(Request $request) {
        
    }



    public function listarFlujos() {
        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success','El listado de flujos se consigion con exito',$data));
    }


}
