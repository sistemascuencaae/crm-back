<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoGaleria;
use Exception;

class TipoGaleriaController extends Controller
{
    public function allTipoGaleria()
    {
        $log = new Funciones();
        try {
            $tiposGaleria = TipoGaleria::where('estado', true)->orderBy("id", "asc")->get();
            $log->logInfo(TipoGaleriaController::class, 'Se listo con exito los tipos de galerias');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tiposGaleria));
        } catch (Exception $e) {
            $log->logError(TipoGaleriaController::class, 'Error al listar los tipos de galerias', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}