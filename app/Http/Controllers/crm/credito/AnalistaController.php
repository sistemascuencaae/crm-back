<?php

namespace App\Http\Controllers\crm\credito;

use App\Events\ComentariosEvent;
use App\Events\CRMEvents;
use App\Events\PruebaEvents;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalistaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }



    public function pruebacambiodiv(Request $request)
    {
        $id = $request->input('id');

        // $dataMovil = DivMovil::find($id);

        $data = DB::select('select * from public.div_movil');

        //echo('resultado: ' . json_encode($dataMovil));

        broadcast(new PruebaEvents($data));
    }










    public function pruebacambiodivDos(Request $request)
    {
        $id = $request->input('text');

        //$dataMovil = DivMovil::find($id);

        //$data = DB::select('select * from public.div_movil');

        //echo('resultado: ' . json_encode($dataMovil));

        broadcast(new CRMEvents($id));

        return response()->json([
            "res" => 200
        ]);
    }





    public function updateDiv(Request $request)
    {
        $data = $request->all();

        for ($i=0; $i < sizeOf($data); $i++) { 
            DB::table('div_movil')
            ->where('div_id', $data[$i]['div_id'])
            ->update(['dato2' =>  $data[$i]['dato2']]);
        }

        $data = DB::select('select * from public.div_movil');

        //echo('resultado: ' . json_encode($dataMovil));

        broadcast(new PruebaEvents($data));
    }














    public function actulizarDatoDiv(Request $request)
    {
        $numCulumn = $request->input('numColumn');
        $array_of_ids = $request->input('ids');
        // $idsUpdate = $request->input('idsUpdate');







        //$sqlDB = DB::select('update public.div_movil set dato2 = '.$dato2.' where div_id in ('.$idsUpdate.')');





        // $data = DB::select('select * from public.div_movil');


        //$result = DivMovil::where('div_id', 1)->where('destination', 'San Diego')->update(['delayed' => 1]);

        // foreach ($array as $i => $value) {


        //     unset($array[$i]);





        // }


        //DB::table('div_movil')->whereIn('div_id', $array_of_ids)->update(['dato2' => $numCulumn]);






        //$data = DB::select('select * from public.div_movil');

        echo ('resultado: ' . json_encode($array_of_ids));


        //broadcast(new PruebaEvents($data));
    }

    public function listaComentarios(Request $request){
        //$userId = $request->input('user_id');
        $divId = $request->input('div_id');
        $data = DB::select('select * from public.comentarios where div_id = '.$divId);
        broadcast(new CRMEvents($data));
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
