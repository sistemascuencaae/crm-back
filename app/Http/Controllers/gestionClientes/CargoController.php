<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\Cargo;
use Exception;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todos los cargos
     */
    public function listAllCargos(Request $request)
    {
        $log = new Funciones();
        try {
            $cargos = Cargo::where('estado', true)->orderBy('nombre', 'asc')->get();

            $log->logInfo(CargoController::class, 'Se listaron los cargos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $cargos));
        } catch (Exception $e) {
            $log->logError(CargoController::class, 'Error al listar los cargos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }
}
