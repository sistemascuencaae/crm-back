<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\crm\TipoTablero;
use Illuminate\Http\Request;

class TipoTableroController extends Controller
{
    public function allTipoTablero()
    {
        $tipoTableros = TipoTablero::orderBy("id", "desc")->get();

        return response()->json([
            "tipoTableros" => $tipoTableros,
        ]);
    }
}
