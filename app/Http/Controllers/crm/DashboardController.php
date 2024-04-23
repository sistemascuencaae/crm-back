<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\views\ProductoClienteView;

class DashboardController extends Controller
{
    //
    public function comprasCliente($entId)
    {
        $log = new Funciones();
        try {
            $data = ProductoClienteView::where('ent_id', $entId)->where('devuelto', false)->get();

            $log->logInfo(DashboardController::class, 'Se listo con exito las compras del cliente');

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (\Throwable $e) {
            $log->logError(DashboardController::class, 'Error al listar las compras del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $e->getMessage()));
        }
    }
}
