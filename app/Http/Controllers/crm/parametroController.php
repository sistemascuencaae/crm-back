<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\parametro;
use Illuminate\Http\Request;

class parametroController extends Controller
{
    public function listParametro()
    {
        $parametro = parametro::orderBy("id", "desc")->get();

        return response()->json([
            "parametro" => $parametro
        ]);
    }
}