<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteOpenceoController extends Controller
{


    public function byCedula($cedula)
    {
        try {
            $data = DB::table('public.cliente')
                ->where('cli_codigo', 'like', '%' . $cedula . '%')
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'El listado de clientes', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Exception', $th->getMessage()));
        }
    }
}
