<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\RequerimientoCaso;
use Exception;
use Illuminate\Http\Request;

class ReqCasoController extends Controller
{
    public function listAll($casoId){
        try {
            $data = RequerimientoCaso::where('caso_id',$casoId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    public function list(){

    }
    public function add(){

    }
}
