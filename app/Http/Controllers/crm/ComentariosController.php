<?php

namespace App\Http\Controllers\crm;

use App\Events\ComentariosEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComentariosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listaComentarios(Request $request){
        //$userId = $request->input('user_id');
        $divId = $request->input('div_id');
        $data = DB::select('select * from public.comentarios where div_id = '.$divId);
        broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }


    public function guardarComentario(Request $request){
        $user_id = $request->input('user_id');
        $comentario = $request->input('comentario');
        $div_id = $request->input('div_id');
        $nombre_usuario = $request->input('nombre_usuario');
        $created_at = $request->input('created_at');
        $updated_at = $request->input('updated_at');


        $data = DB::table('comentarios')->insert([
            'user_id' => $user_id,
            'comentario' => $comentario,
            'nombre_usuario' => $nombre_usuario,
            'div_id' => $div_id,
            'created_at' => $created_at,
        ]);

        $data = DB::select('select * from public.comentarios where div_id = '.$div_id);
        broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }



}
