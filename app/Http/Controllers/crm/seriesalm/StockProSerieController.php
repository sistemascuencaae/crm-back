<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ContratoGex;
use App\Models\crm\series\Despacho;
use App\Models\crm\series\Inventario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockProSerieController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'loadInitialData',
        ]]);
    }

    public function loadInitialData($bodId){
        //(select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id )
        try {
            $datosSeries = DB::select("SELECT tt.*, (stock_actual-stock_serie) as diferencia from (
            select pro_id, pro_codigo, pro_nombre, bod_id, bodega, stock_actual,
            (select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id ) as stock_serie
            from av_stock_producto_bodega sbo) tt where tt.bod_id = ?",[$bodId]);

            $data = (object)[
                "productoSeries" => $datosSeries
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }


}
