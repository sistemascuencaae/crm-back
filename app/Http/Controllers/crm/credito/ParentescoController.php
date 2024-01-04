<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Parentesco;
use Exception;
use Illuminate\Http\Request;

class ParentescoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listParentesco()
    {
        $log = new Funciones();
        try {
            $respuesta = Parentesco::orderBy("id", "asc")->get();

            $log->logInfo(ParentescoController::class, 'Se listo con exito los parentescos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
        } catch (Exception $e) {
            $log->logError(ParentescoController::class, 'Error al listar los parentescos', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}