<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\crm\Entidad;

class EntidadController extends Controller
{
    public function list($cedula)
    {
        $data = Entidad::with('cliente', 'direccion')->where('ent_identificacion', $cedula)->get();

        return response()->json(["message" => 200, "data" => $data]);
    }
}