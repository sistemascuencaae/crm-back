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

    public function loadInitialData($bodId)
    {
        //(select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id )
        try {
            $bodega = DB::selectOne("SELECT bod_id, bod_nombre, ubi_nombre from public.bodega b
            left join public.ubicacion u on u.ubi_id = b.ubi_id where bod_id = ? limit 1;", [$bodId]);

            $datosSeries = DB::select("SELECT tt.*, (stock_actual-stock_serie) as diferencia from (
            select pro_id, pro_codigo, pro_nombre, bod_id, bodega, stock_actual,
            (select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id ) as stock_serie
            from av_stock_producto_bodega sbo) tt where tt.bod_id = ?", [$bodId]);

            $data = (object)[
                "bodega" => $bodega,
                "productoSeries" => $datosSeries
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }


    public function getReporteBodegas()
    {
        //(select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id )
        try {
            $datosSeries = DB::select("SELECT distinct(tt.bodega), sum(stock_actual) as stock_productos, sum(stock_serie) as stock_series from (
            select pro_id, pro_codigo, pro_nombre, bod_id, bodega, stock_actual,
            (select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id ) as stock_serie
            from av_stock_producto_bodega sbo) tt group by 1;");

            $data = (object)[
                "productoSeries" => $datosSeries
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }


    public function getSerieDesCliente($serie, $bodId)
    {
        try {
            $data = DB::selectOne("SELECT ss.serie, p.pro_codigo, p.pro_nombre from crm.stock_pro_serie ss
                 inner join public.producto p on p.pro_id = ss.pro_id and ss.bod_id = ? where serie = ?;", [$bodId, $serie]);

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Serie no existe.', []));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function despacharSerie($serie, $bodId)
    {
        try {

            $data = DB::transaction(function () use ($serie, $bodId){
                $dataSerie = DB::selectOne("SELECT  * from crm.stock_pro_serie ss where bod_id = ? and serie = ?", [$bodId, $serie]);
                $serieElim = DB::insert("INSERT INTO crm.despacho_serie(bod_id, pro_id, serie) VALUES( ?, ?, ?);",[$dataSerie->bod_id, $dataSerie->pro_id, $dataSerie->serie]);
                DB::delete("DELETE FROM crm.stock_pro_serie WHERE id = ?", [$dataSerie->id]);
                return true;
            });

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con exito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Serie no existe.', []));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }
}
