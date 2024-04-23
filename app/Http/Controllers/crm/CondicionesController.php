<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\Condiciones;
use Exception;

class CondicionesController extends Controller
{
    public function listCondiciones()
    {
        $log = new Funciones();

        try {
            $data = Condiciones::orderBy("id", "desc")->get();

            $log->logInfo(CondicionesController::class, 'Se listo con exito las condiciones');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(CondicionesController::class, 'Error al listar las condiciones', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}