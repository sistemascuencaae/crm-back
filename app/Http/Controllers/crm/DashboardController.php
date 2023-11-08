<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\views\ProductoClienteView;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function comprasCliente($entId)
    {
        try {
            $data = ProductoClienteView::where('ent_id', $entId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $th->getMessage()));
        }
    }
}
