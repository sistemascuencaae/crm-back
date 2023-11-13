<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableroProcesosController extends Controller
{

    public function list($tabId)
    {
        try {
            $data = DB::select("SELECT * from crm.casos_porusuario where tablero_id = ?", [$tabId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar formularios.', $th->getMessage()));
        }
    }
}
