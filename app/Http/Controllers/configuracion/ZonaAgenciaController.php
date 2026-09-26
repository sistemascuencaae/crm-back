<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\ZonaAgencia;
use Exception;

class ZonaAgenciaController extends Controller
{
    public function __construct() {}

    public function listZonasAgenciaActivas()
    {
        try {
            $data = ZonaAgencia::where('estado', true)->orderBy('nombre', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
