<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\CodigoQr;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CodigoQrController extends Controller
{
    public function __construct()
    {
    }

    public function listAllCodigosQr()
    {
        try {
            $data = CodigoQr::get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}