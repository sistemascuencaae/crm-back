<?php

namespace App\Http\Controllers\crediGestion;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;
use Request;

class CrediGestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [

            ]
        ]);
    }

    public function alm_facturas($anio, $mes, $dia)
    {
        try {
            $data = DB::select('SELECT * FROM dashboard.af_migracion_cartera_historica_api(?, ?, ?)', [$anio, $mes, $dia]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
