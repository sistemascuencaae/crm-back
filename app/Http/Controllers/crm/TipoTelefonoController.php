<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoTelefono;
use Exception;

class TipoTelefonoController extends Controller
{
    private $log;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->log = new Funciones();
    }

    public function listTipoTelefono()
    {
        try {
            $respuesta = TipoTelefono::orderBy("id", "asc")->get();

            $this->log->logInfo(TipoTelefonoController::class, 'Se listo con exito los tipos de telefonos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
        } catch (Exception $e) {
            $this->log->logError(TipoTelefonoController::class, 'Error al listar los tipos de telefonos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}