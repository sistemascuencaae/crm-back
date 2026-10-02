<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KardexSeriesController extends Controller
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

    public function kardexSeries($fecIni, $fecFin, $bodega, $producto, $tipo, $serie)
    {
        $data = DB::select("select TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    'INVENTARIO' as tipo_mov,
                                    1 as orden,
                                    c.numero,
                                    b.bod_nombre,
                                    '' as numero_rel,
                                    concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                    d.serie,
                                    d.tipo,
                                    (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                            from gex.cinventario c join gex.dinventario d on c.numero = d.numero
                                                join producto p on d.pro_id  = p.pro_id
                                                join bodega b on c.bod_id = b.bod_id
                            where c.estado = 'P' and cast(c.fecha as date) between '" . $fecIni . "' and '" . $fecFin . "'
                                    and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                    and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                    and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                    and (d.serie = '" . $serie. "' or '" . $serie . "' = 'T')
                            union all
                            select TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    'INGRESO' as tipo_mov,
                                    2 as orden,
                                    c.numero,
                                    b.bod_nombre,
                                    (case when c.cmo_id is null then concat(c5.cti_sigla,' - ', a3.alm_codigo, ' - ', p3.pve_numero, ' - ',  c3.cfa_numero) else concat(c4.cti_sigla,' - ', a2.alm_codigo, ' - ', p2.pve_numero, ' - ',  c2.cmo_numero) end) as numero_rel,
                                    concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                    d.serie,
                                    d.tipo,
                                    (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                            from gex.cpreingreso c join gex.dpreingreso d on c.numero = d.numero
                                                join producto p on d.pro_id  = p.pro_id
                                                join bodega b on c.bod_id = b.bod_id
                                                left outer join cmovinv c2 on c.cmo_id = c2.cmo_id
                                                left outer join cfactura c3 on c.cfa_id = c3.cfa_id
                                                left outer join puntoventa p2 on c2.pve_id = p2.pve_id
                                                left outer join almacen a2 on p2.alm_id = a2.alm_id
                                                left outer join puntoventa p3 on c3.pve_id = p3.pve_id
                                                left outer join almacen a3 on p3.alm_id = a3.alm_id
                                                left outer join ctipocom c4 on c2.cti_id = c4.cti_id
                                                left outer join ctipocom c5 on c3.cti_id = c5.cti_id
                            where (c.cmo_id is not null or c.cfa_id is not null) and c.estado = 'A' and cast(c.fecha as date) between '" . $fecIni . "' and '" . $fecFin . "'
                                    and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                    and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                    and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                    and (d.serie = '" . $serie. "' or '" . $serie . "' = 'T')
                            union all
                            select TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    'DESPACHO' as tipo_mov,
                                    3 as orden,
                                    c.numero,
                                    b.bod_nombre,
                                    (case when c.cmo_id is null then concat(c5.cti_sigla,' - ', a3.alm_codigo, ' - ', p3.pve_numero, ' - ',  c3.cfa_numero) else concat(c4.cti_sigla,' - ', a2.alm_codigo, ' - ', p2.pve_numero, ' - ',  c2.cmo_numero) end) as numero_rel,
                                    concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                    d.serie,
                                    d.tipo,
                                    (case when d.tipo = 'N' then 1 else 0.5 end) * -1 as cantidad
                            from gex.cdespacho c join gex.ddespacho d on c.numero = d.numero
                                                join producto p on d.pro_id  = p.pro_id
                                                join bodega b on c.bod_id = b.bod_id
                                                left outer join cmovinv c2 on c.cmo_id = c2.cmo_id
                                                left outer join cfactura c3 on c.cfa_id = c3.cfa_id
                                                left outer join puntoventa p2 on c2.pve_id = p2.pve_id
                                                left outer join almacen a2 on p2.alm_id = a2.alm_id
                                                left outer join puntoventa p3 on c3.pve_id = p3.pve_id
                                                left outer join almacen a3 on p3.alm_id = a3.alm_id
                                                left outer join ctipocom c4 on c2.cti_id = c4.cti_id
                                                left outer join ctipocom c5 on c3.cti_id = c5.cti_id
                            where c.estado = 'A' and cast(c.fecha as date) between '" . $fecIni . "' and '" . $fecFin . "'
                                    and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                    and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                    and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                    and (d.serie = '" . $serie. "' or '" . $serie . "' = 'T')
                            union all
                            select TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    'TRASPASO ENTRANTE' as tipo_mov,
                                    3 as orden,
                                    c.numero,
                                    b.bod_nombre,
                                    (case when c.cmo_id is null then concat(c5.cti_sigla,' - ', a3.alm_codigo, ' - ', p3.pve_numero, ' - ',  c3.cfa_numero) else concat(c4.cti_sigla,' - ', a2.alm_codigo, ' - ', p2.pve_numero, ' - ',  c2.cmo_numero) end) as numero_rel,
                                    concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                    d.serie,
                                    d.tipo,
                                    (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                            from gex.cdespacho c join gex.ddespacho d on c.numero = d.numero
                                                join producto p on d.pro_id  = p.pro_id
                                                left outer join cmovinv c2 on c.cmo_id = c2.cmo_id
                                                left outer join bodega b on c2.bod_id_fin = b.bod_id
                                                left outer join cfactura c3 on c.cfa_id = c3.cfa_id
                                                left outer join puntoventa p2 on c2.pve_id = p2.pve_id
                                                left outer join almacen a2 on p2.alm_id = a2.alm_id
                                                left outer join puntoventa p3 on c3.pve_id = p3.pve_id
                                                left outer join almacen a3 on p3.alm_id = a3.alm_id
                                                left outer join ctipocom c4 on c2.cti_id = c4.cti_id
                                                left outer join ctipocom c5 on c3.cti_id = c5.cti_id
                            where c.estado = 'A' and cast(c.fecha as date) between '" . $fecIni . "' and '" . $fecFin . "'
                                    and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                    and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                    and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                    and (d.serie = '" . $serie . "' or '" . $serie . "' = 'T')
                                    and c2.cti_id in (select cti_id from gex.doc_presenta where opcion = 'KAR')
                            union all
                            select null,
                                    'SALDO',
                                    0,
                                    0,
                                    bod_nombre,
                                    null,
                                    producto,
                                    serie,
                                    tipo,
                                    sum(cantidad)
                            from (select b.bod_nombre,
                                            concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                            d.serie,
                                            d.tipo,
                                            (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                                    from gex.cinventario c join gex.dinventario d on c.numero = d.numero
                                                        join producto p on d.pro_id  = p.pro_id
                                                        join bodega b on c.bod_id = b.bod_id
                                    where c.estado = 'P' and cast(c.fecha as date) < '" . $fecIni . "'
                                            and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                            and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                            and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                            and (d.serie = '" . $serie. "' or '" . $serie . "' = 'T')
                                    union all
                                    select b.bod_nombre,
                                            concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                            d.serie,
                                            d.tipo,
                                            (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                                    from gex.cpreingreso c join gex.dpreingreso d on c.numero = d.numero
                                                        join producto p on d.pro_id  = p.pro_id
                                                        join bodega b on c.bod_id = b.bod_id
                                    where (c.cmo_id is not null or c.cfa_id is not null) and c.estado = 'A' and cast(c.fecha as date) < '" . $fecIni . "'
                                            and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                            and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                            and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                            and (d.serie = '" . $serie. "' or '" . $serie . "' = 'T')
                                    union all
                                    select b.bod_nombre,
                                            concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                            d.serie,
                                            d.tipo,
                                            (case when d.tipo = 'N' then 1 else 0.5 end) * -1 as cantidad
                                    from gex.cdespacho c join gex.ddespacho d on c.numero = d.numero
                                                        join producto p on d.pro_id  = p.pro_id
                                                        join bodega b on c.bod_id = b.bod_id
                                    where c.estado = 'A' and cast(c.fecha as date) < '" . $fecIni . "'
                                            and (b.bod_id = " . $bodega . " or " . $bodega . " = 0)
                                            and (p.pro_id = " . $producto . " or " . $producto . " = 0)
                                            and (d.tipo = '" . $tipo . "' or '" . $tipo . "' = 'T')
                                            and (d.serie = '" . $serie. "' or '" . $serie . "' = 'T')) as tabla
                            group by bod_nombre, producto, serie, tipo
                            order by bod_nombre, producto, serie, orden, fecha, numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}
