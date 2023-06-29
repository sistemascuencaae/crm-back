<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\UsuarioDynamo;
use App\Models\User;

class UsersOpenceoController extends Controller
{
    public function listAnalistas()
    {
        $data = User::with('UsuarioDynamo')->where('usu_tipo_analista', 1)->get();
        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }

    public function listEmpleadosActivos()
    {
        $empleados = UsuarioDynamo::orderBy("usu_id", "asc")->where('usu_activo', true)->get();

        return response()->json(RespuestaApi::returnResultado('success', 'Lista de empleados activos', [
            "empleados" => $empleados->map(function ($emp) {
                return [
                    "usu_id" => $emp->usu_id,
                    "usu_alias" => $emp->usu_alias
                ];
            }),
        ]));
    }
}