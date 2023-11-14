<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Etiqueta;
use App\Models\crm\Parentesco;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentescoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listParentesco()
    {
        try {
            $respuesta = Parentesco::orderBy("id", "asc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}