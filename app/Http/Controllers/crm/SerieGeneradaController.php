<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SerieGeneradaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function buscarSerieGex(Request $request)
    {
        try {
            $producto_serie = DB::selectOne("select p.serie from gex.producto_serie p
                                            where p.serie = '$request->serie';");

            $stock_serie = DB::selectOne("select s.serie from gex.stock_serie s
                                            where s.serie = '$request->serie';");

            $dinventario = DB::selectOne("select d.serie from gex.dinventario d
                                            where d.serie = '$request->serie';");

            $dpreingreso = DB::selectOne("select d.serie from gex.dpreingreso d
                                            where d.serie = '$request->serie';");

            $ddespacho = DB::selectOne("select d.serie from gex.ddespacho d
                                            where d.serie = '$request->serie';");

            // echo json_encode($producto_serie) . ' / ' . json_encode($stock_serie) . ' / ' . json_encode($dinventario) . ' / ' . json_encode($dpreingreso) . ' / ' . json_encode($ddespacho);
            if ($producto_serie || $stock_serie || $dinventario || $dpreingreso || $ddespacho) {
                return response()->json(RespuestaApi::returnResultado('error', 'Ya esta registrada esta serie N° ' . $request->serie, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'No esta registrada esta serie N° ' . $request->serie, ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}