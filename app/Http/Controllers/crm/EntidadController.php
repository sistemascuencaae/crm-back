<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Cliente;
use App\Models\crm\Direccion;
use App\Models\crm\Entidad;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntidadController extends Controller
{
    public function byId($id)
    {
        $data = Entidad::with('cliente', 'direccion','clientefae','referenanexo')->where('ent_id', $id)->first();
        //$clifae = DB::select("select * from public.av_clientes_reiterativos c where c.cedula = '0100091560';");

        return response()->json(["message" => 200, "data" => $data]);
    }

    public function byCedula($cedula){
        $entidad = Entidad::with('cliente')->where('ent_identificacion', $cedula)->first();
        if($entidad){
            return response()->json(RespuestaApi::returnResultado('success', 'El cliente encontrado', $entidad));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El cliente no existe', []));
        }
    }

    public function editEntidad(Request $request)
    {
        $ent = $request->input('entidad');
        $cli = $request->input('cliente');
        $dir = $request->input('direccion');

        $ent_id = $request->input('entidad.ent_id');
        $dir_id = $request->input('direccion.dir_id');
        $cli_id = $request->input('cliente.cli_id');

        try {
            $resultDBtransaction = DB::transaction(function () use ($ent_id, $ent, $dir_id, $dir, $cli_id, $cli) {
                $entidad = Entidad::find($ent_id);
                $direccion = Direccion::find($dir_id);
                $cliente = Cliente::find($cli_id);

                $entidad->update($ent);
                $direccion->update($dir);
                $cliente->update($cli);
            });
            return response()->json(["Actualizado" => 200]);
        } catch (Exception $e) {
            echo ($e->getMessage());
        }

    }

}
// public function editEntidad(Request $request)
// {
//     try {
//         $entidad = Entidad::findOrFail($request->input('ent_id'));

//         $entidad->update($request->all());


//         return response()->json(["Entidad" => $entidad,]);
//     } catch (Exception $e) {
//         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
//     }
// }

// public function editDireccion(Request $request)
// {
//     try {
//         $direccion = Direccion::findOrFail($request->input('dir_id'));

//         $direccion->update($request->all());
//         // $entidad->update([
//         //     // "titulo" => $titulo,
//         //     "ent_nombres" => $request->ent_nombres,
//         // ]);

//         return response()->json(["Direccion" => $direccion,]);
//     } catch (Exception $e) {
//         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
//     }
// } 