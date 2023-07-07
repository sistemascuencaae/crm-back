<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteOpenceoController extends Controller
{

    public function list($parametro)
    {

        try {
            $data = DB::select("SELECT * FROM public.cliente WHERE UPPER(CONCAT(cli_codigo,'-',ent_nombre_comercial)) like '%".$parametro."%' limit 100");
            return response()->json(RespuestaApi::returnResultado('success', 'El listado de clientes', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Exception', $th->getMessage()));
        }
    }



    //CON PAGIONADOR
    // public function list(Request $request)
    // {

    //     try {

    //     $perPage = 20;
    //     $page = 2;

    //     $clientesList = DB::table('public.cliente')->whereRaw("UPPER(CONCAT(cli_codigo,'-',ent_nombre_comercial)) like '%010719%'")->paginate($perPage, ['*'], 'page', $page);
    //     return response()->json(RespuestaApi::returnResultado('success', 'El listado de clientes', $clientesList));
    //     } catch (\Throwable $th) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Exception', $th->getMessage()));
    //     }

    // }


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
