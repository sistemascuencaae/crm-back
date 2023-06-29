<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\User;

class UsersOpenceoController extends Controller
{
    public function listAnalistas()
    {
        $data = User::with('UsuarioDynamo')->where('usu_tipo_analista', 1)->get();
        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }

    public function listUsuariosActivos()
    {
        $usuarios = User::orderBy("id", "asc")->get();

        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios activos', [
            "usuarios" => $usuarios->map(function ($usuario) {
                return [
                    "id" => $usuario->id,
                    "name" => $usuario->name
                ];
            }),
        ]));
    }
}