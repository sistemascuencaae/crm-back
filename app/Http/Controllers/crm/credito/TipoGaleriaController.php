<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Models\crm\TipoGaleria;
use Illuminate\Http\Request;

class TipoGaleriaController extends Controller
{
    public function index()
    {
        $tiposGaleria = TipoGaleria::orderBy("id", "asc")->get();

        return response()->json([
            "tiposGaleria" => $tiposGaleria,
        ]);
    }
}