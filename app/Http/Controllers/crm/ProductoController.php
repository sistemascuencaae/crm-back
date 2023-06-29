<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function buscaProducto(Request $request){
        $codigo = $request->input('code');
        $descripcion = $request->input('description');

        $data = DB::select("select pro_id, pro_codigo, pro_nombre from public.producto where pro_codigo = '".$codigo."'");

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'El producto encontrado', $data));
        }else{
            $data = DB::select("select pro_id, pro_codigo, pro_nombre from public.producto where pro_nombre like '%".$descripcion."%'");

            if($data){
                return response()->json(RespuestaApi::returnResultado('success', 'El producto encontrado', $data));
            }else{
                return response()->json(RespuestaApi::returnResultado('error', 'El producto no existe', []));
            }
        }
    }

}