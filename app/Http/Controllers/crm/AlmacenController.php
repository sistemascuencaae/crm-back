<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;

class AlmacenController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' =>
        [
            'listAlmacenCrm',
        ]]);
    }

    public function listAlmacenCrm()
    {
        try {
            $data = DB::SELECT("SELECT * FROM crm.almacen");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}