<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\crm\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    public function allDepartamento()
    {
        $departamentos = Departamento::orderBy("id", "desc")->get();

        return response()->json([
            "departamentos" => $departamentos,
        ]);
    }
}