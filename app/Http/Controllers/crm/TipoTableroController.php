<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoTablero;
use Exception;
use Illuminate\Http\Request;

class TipoTableroController extends Controller
{
    public function allTipoTablero()
    {
        try {
            $tipoTableros = TipoTablero::orderBy("id", "desc")->get();

            // return response()->json([
            //     "tipoTableros" => $tipoTableros,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tipoTableros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}