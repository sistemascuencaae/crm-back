<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioFechaSeriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function productos()
    {
        $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta, pc.tipo_servicio as tipo from producto p left outer join gex.producto_config pc  on p.pro_id = pc.pro_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function bodegas()
    {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b order by presenta");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function invFecSeries($fecCorte, $bodega, $producto)
    {
        $data = DB::select("select bod_nombre, producto, sum(cantidad) cant
                                from (
                                select b.bod_nombre,
                                                concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                                (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                                from gex.cinventario c join gex.dinventario d on c.numero = d.numero 
                                                                        join producto p on d.pro_id  = p.pro_id
                                                                        join bodega b on c.bod_id = b.bod_id
                                where d.procesado = 'S' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                                and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                                and (p.pro_id = " . $producto . " or 0 = " . $producto . ")
                                union all
                                select b.bod_nombre,
                                                concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                                (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                                from gex.cpreingreso c join gex.dpreingreso d on c.numero = d.numero 
                                                                        join producto p on d.pro_id  = p.pro_id
                                                                        join bodega b on c.bod_id = b.bod_id
                                where (c.cmo_id is not null or c.cfa_id is not null) and c.estado = 'A' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                                and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                                and (p.pro_id = " . $producto . " or 0 = " . $producto . ")
                                union all
                                select b.bod_nombre,
                                                concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                                (case when d.tipo = 'N' then 1 else 0.5 end) * -1 as cantidad
                                from gex.cdespacho c join gex.ddespacho d on c.numero = d.numero 
                                                                        join producto p on d.pro_id  = p.pro_id
                                                                        join bodega b on c.bod_id = b.bod_id
                                where c.estado = 'A' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                                and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                                and (p.pro_id = " . $producto . " or 0 = " . $producto . ")
                                union all
                                select b.bod_nombre,
                                                concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                                (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                                from gex.cdespacho c join gex.ddespacho d on c.numero = d.numero 
                                                                        join producto p on d.pro_id  = p.pro_id
                                                						left outer join cmovinv c2 on c.cmo_id = c2.cmo_id
                                                						left outer join bodega b on c2.bod_id_fin = b.bod_id
                                where c.estado = 'A' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                                and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                                and (p.pro_id = " . $producto . " or 0 = " . $producto . ")
                                    			and c2.cti_id in (select cti_id from gex.doc_presenta where opcion = 'KAR')) as tabla
                                group by bod_nombre, producto
                                order by bod_nombre, producto");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}