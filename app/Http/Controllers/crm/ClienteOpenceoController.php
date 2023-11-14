<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\AvCasoCliente;
use App\Models\crm\Caso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\Cast\Object_;

class ClienteOpenceoController extends Controller
{

    public function list($parametro)
    {

        try {
            $data = DB::select("SELECT * FROM public.cliente WHERE UPPER(CONCAT(cli_codigo,'-',ent_nombre_comercial)) like '%" . $parametro . "%' limit 100");
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


    public function clienteCasoList($tabId)
    {
        try {


            //               {
            //     name: "Congo",
            //     series: [
            //       { value: 7, name: "Thu 15" },
            //       { value: 4, name: "Sat 17" },
            //       { value: 2, name: "Mon 19" },
            //       { value: 12, name: "Wed 21" },
            //       { value: 32, name: "Fri 23" },
            //     ],
            //   },




            $listaAvCC = AvCasoCliente::where('tab_id', $tabId)->get();

            $dataCharLine = DB::select("SELECT ta.id as tab_id, ta.nombre as tab_nombre, count(*) as num_caso_tablero, us.name as usu_name   from crm.caso cas
            inner join crm.users us on us.id = cas.user_id
            inner join crm.fase fa on fa.id = cas.fas_id
            inner join crm.tablero ta on ta.id = fa.tab_id where ta.id = {$tabId} group by (ta.id, ta.nombre, us.name);");

            $data = (object) [
                'listaAvCC' => $listaAvCC,
                'dataCharLine' => $dataCharLine,
            ];





            return response()->json(RespuestaApi::returnResultado('success', 'El listado de clientes', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Exception', $th->getMessage()));
        }
    }
}
