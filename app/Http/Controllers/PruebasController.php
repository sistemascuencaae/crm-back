<?php

namespace App\Http\Controllers;

use App\Events\NewTrade;
use App\Events\PruebaEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PruebasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }


    public function pruebaswebsokets(Request $request)
    {
        event(new PruebaEvents($request->msg));
        return response()->json(["message"=> 200]);
    }

    // public function cambioDiv(Request $request)
    // {
    //     event(new NewTrade($request->msg));
    //     return response()->json(["message"=> 200]);
    // }

    public function pruebasInsert(Request $request) {
        $dato1 = $request->input('dato1');

   



            DB::insert('insert into div_movil (dato1) values(?)',[$dato1]);

            


        $results = DB::select('select * from div_movil order by div_id desc limit 1');


        //echo('result: '.json_encode($results));
        return response()->json([
            "message"=> 200,
            "data"=> $results

        ]);
     }



    public function updatePositionDiv(Request $request) {

        




    }



}
