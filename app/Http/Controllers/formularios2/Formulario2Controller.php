<?php

namespace App\Http\Controllers\formularios2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoCaso;
use App\Models\Formulario\Formulario;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Formulario2Controller extends Controller
{
    public function __construct()
    {
    }

    public function listFormByTipoCaso($tipoCaso_id)
    {
        try {
            $data = TipoCaso::where('id', $tipoCaso_id)
                ->with('formularios.formulario_campo', 'formularios.formulario_campo.campo_opcion')
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

}
