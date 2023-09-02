<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoEstado;
use App\Models\crm\TipoGaleria;
use Exception;

class TipoEstadoController extends Controller
{
    public function allTipoEstado()
    {
        try {
            $tiposEstados= TipoEstado::orderBy("id", "asc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tiposEstados));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}