<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;

class ReasignarCasoController extends Controller
{
    public function reasignarCaso(Request $request){


        try {


            $casoId = $request->input('casoId');
            $tableroActual = $request->input('casoId');







           // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $dataInput));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }
}
