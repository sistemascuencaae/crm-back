<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openCeo\CPedidoProforma;
use Exception;
use Illuminate\Http\Request;

class PedidoMovilController extends Controller
{
    //
    public function getPedidoById($cppId)
    {
        try {
            $data = CPedidoProforma::with('dpedidoProforma')->where('cpp_id', $cppId)->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
