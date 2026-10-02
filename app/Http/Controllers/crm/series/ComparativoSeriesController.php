<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComparativoSeriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function inventarios($bodega)
    {
        $data = DB::select("select c.numero, concat(c.numero, ' - ', c.responsable, ' - ', TO_CHAR(c.fecha::date, 'dd/mm/yyyy')) as inventario
                            from gex.cinventario c
                            where c.bod_id = " . $bodega);

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function bodegas()
    {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b order by presenta");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function comparativoSeries($fecCorte, $bodega, $numero)
    {
        $data = DB::select("select coalesce(movi.bod_nombre,inve.bodegai) as bodega, coalesce(movi.producto, inve.productoi) as producto, coalesce(movi.seriep, inve.seriepi) as serie,
                                    sum(coalesce(movi.cantidad, 0)) as movimientos, max(coalesce(inve.cantidadi, 0)) as inventario
                            from (
                                    select b.bod_nombre,
                                            concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                            concat(d.serie, ' - ', (case d.tipo when 'E' then 'EVAPORADOR' when 'C' then 'COMPRESOR' else '' end)) as seriep,
                                            (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad,
                                            c.bod_id, d.pro_id, d.serie, d.tipo
                                    from gex.cinventario c join gex.dinventario d on c.numero = d.numero 
                                                        join producto p on d.pro_id  = p.pro_id
                                                        join bodega b on c.bod_id = b.bod_id
                                    where d.procesado = 'S' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                            and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                    union all
                                    select b.bod_nombre,
                                            concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                            concat(d.serie, ' - ', (case d.tipo when 'E' then 'EVAPORADOR' when 'C' then 'COMPRESOR' else '' end)) as seriep,
                                            (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad,
                                            c.bod_id, d.pro_id, d.serie, d.tipo
                                    from gex.cpreingreso c join gex.dpreingreso d on c.numero = d.numero 
                                                        join producto p on d.pro_id  = p.pro_id
                                                        join bodega b on c.bod_id = b.bod_id
                                    where (c.cmo_id is not null or c.cfa_id is not null) and c.estado = 'A' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                            and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                    union all
                                    select b.bod_nombre,
                                            concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                            concat(d.serie, ' - ', (case d.tipo when 'E' then 'EVAPORADOR' when 'C' then 'COMPRESOR' else '' end)) as seriep,
                                            (case when d.tipo = 'N' then 1 else 0.5 end) * -1 as cantidad,
                                            c.bod_id, d.pro_id, d.serie, d.tipo
                                    from gex.cdespacho c join gex.ddespacho d on c.numero = d.numero 
                                                        join producto p on d.pro_id  = p.pro_id
                                                        join bodega b on c.bod_id = b.bod_id
                                    where c.estado = 'A' and cast(c.fecha as date) <= '" . $fecCorte . "'
                                            and (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")) as movi right outer join
                                (select b.bod_nombre as bodegai,
                                        concat(p.pro_codigo,' - ',p.pro_nombre) as productoi,
                                        concat(d.serie, ' - ', (case d.tipo when 'E' then 'EVAPORADOR' when 'C' then 'COMPRESOR' else '' end)) as seriepi,
                                        (case when d.tipo = 'N' then 1 else 0.5 end) as cantidadi,
                                        c.bod_id, d.pro_id, d.serie, d.tipo
                                from gex.cinventario c join gex.dinventario d on c.numero = d.numero 
                                                    join producto p on d.pro_id  = p.pro_id
                                                    join bodega b on c.bod_id = b.bod_id
                                where (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                        and (c.numero = " . $numero . " or 0 = " . $numero . ")) as inve on movi.bod_id = inve.bod_id and movi.pro_id = inve.pro_id and movi.serie = inve.serie and movi.tipo = inve.tipo
                            group by movi.bod_nombre,inve.bodegai,movi.producto,inve.productoi,movi.seriep,inve.seriepi
                            order by bodega, producto, serie");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}