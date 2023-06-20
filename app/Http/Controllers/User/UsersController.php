<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\UsuarioDynamo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{


    public function listAnalistas()
    {
        $data = User::with('UsuarioDynamo')->where('usu_tipo_analista',1)->get();
        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }







}

