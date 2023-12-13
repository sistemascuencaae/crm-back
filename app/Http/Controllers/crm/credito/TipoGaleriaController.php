<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoGaleria;
use Exception;

class TipoGaleriaController extends Controller
{
    public function allTipoGaleria()
    {
        try {
            $tiposGaleria = TipoGaleria::where('estado', true)->orderBy("id", "asc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tiposGaleria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}