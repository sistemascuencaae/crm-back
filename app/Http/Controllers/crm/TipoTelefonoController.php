<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Etiqueta;
use App\Models\crm\Parentesco;
use App\Models\crm\TipoTelefono;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoTelefonoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listTipoTelefono()
    {
        try {
            $respuesta = TipoTelefono::orderBy("id", "asc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}