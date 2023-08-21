<?php

namespace App\Http\Controllers\crm;

use App\Events\ComentariosEvent;
use App\Http\Controllers\Controller;
use App\Models\crm\Comentarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComentariosController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function listaComentarios(Request $request){
        //$userId = $request->input('user_id');
        $caso_id = $request->input('caso_id');
        $data = DB::select('select * from crm.comentarios where caso_id = '.$caso_id);
        //broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }


    public function guardarComentario(Request $request){

        $coment = Comentarios::create($request->all());
        $data = DB::select('select * from crm.comentarios where caso_id = '.$coment->caso_id);
        broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }



}
