<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentasProductosGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function lineas()
    {
        $data = DB::select("select tp.tpr_id, tp.tpr_nombre from tipo_producto tp");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function lineasMotos()
    {
        $data = DB::select("select tp.tpr_id, tp.tpr_nombre from tipo_producto tp where tp.tpr_nombre like 'MOTO %'");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function productos($tipoProd)
    {
        $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.tpr_id = " . $tipoProd);

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function almacenes()
    {
        $data = DB::select("select a.alm_id, a.alm_nombre
                            from almacen a
                            union all
                            select p.pve_id, concat(a.alm_nombre, ' - ', p.pve_nombre)
                            from almacen a join puntoventa p on a.alm_id = p.alm_id
                            where a.alm_id = 3
                            order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function formasPago()
    {
        $data = DB::select("select p.pol_id, p.pol_nombre from politica p where pol_tipocli = 1 order by p.pol_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function vendedores()
    {
        $data = DB::select("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id order by en.ent_nombres");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function VentasProductosGex($tipoProd, $producto, $sucursal, $formaPago, $vendedor, $fecIni, $fecFin)
    {
        $data = DB::select("select c.cfa_fecha,
                                    (case when pc.tipo_servicio = 'M' then pv.pve_nombre else a.alm_nombre end) as sucursal,
                                    pv.pve_nombre,
                                    concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                    en.ent_identificacion as ci_vendedor,
                                    concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', pv.pve_numero, ' - ',  c.cfa_numero) as num_factura,
                                    tp.tpr_nombre as tipo_producto,
                                    concat(p.pro_codigo, ' - ', p.pro_nombre) as producto,
                                    v.dfac_costoprecio as venta,
                                    coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) as gex,
                                    v.dfac_costoprecio + coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) as subtotal,
                                    v.dfac_dsc1y2 as descuentos,
                                    v.dfac_iva as impuesto,
                                    v.dfac_costoprecio + coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) - v.dfac_dsc1y2 + v.dfac_iva as venta_neta,
                                    v.intereses as interes,
                                    v.dfac_costoprecio + coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) - v.dfac_dsc1y2 + v.dfac_iva + v.intereses as total,
                                    po.pol_nombre as forma_pago,
                                    concat(ftp.factpa_plazo, ' ', ftp.factpa_tiempo) as plazo,
                                    mar.mar_nombre
                            from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                            join v_dfacturacompleto_almespa v on d.cfa_id = v.cfa_id and d.dfac_id = v.dfac_id
                                            left outer join fac_tipo_pago ftp on c.cfa_id = ftp.cfa_id
                                            join producto p on d.pro_id = p.pro_id and pro_inventario = true
                                            join marca mar on mar.mar_id = p.mar_id
                                            join tipo_producto tp on p.tpr_id = tp.tpr_id
                                            left outer join politica po on c.pol_id = po.pol_id
                                            join puntoventa pv on c.pve_id = pv.pve_id
                                            join ctipocom t on c.cti_id = t.cti_id
                                            join almacen a on pv.alm_id = a.alm_id
                                            join empleado e on c.vnd_id = e.emp_id
                                            join entidad en on e.ent_id = en.ent_id
                                            left outer join gex.producto_config pc on p.pro_id = pc.pro_id
                            where cast(c.cfa_fecha as date) between '" . $fecIni . "' and '" . $fecFin . "'
                                    and (tp.tpr_id = " . $tipoProd . " or 0 = " . $tipoProd . ")
                                    and (p.pro_id = " . $producto . " or 0 = " . $producto . ")
                                    and ((case when pc.tipo_servicio = 'M' then pv.pve_id else a.alm_id end) = " . $sucursal . " or 0 = " . $sucursal . ")
                                    and (po.pol_id = " . $formaPago . " or 0 = " . $formaPago . ")
                                    and (e.emp_id= " . $vendedor . " or 0 = " . $vendedor . ")
                                    and exists (select 1 from gex.rel_linea_gex rlg where rlg.tpr_id = tp.tpr_id)
                            order by c.cfa_fecha, sucursal, vendedor, num_factura");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function VentasMotosGex($tipoProd, $producto, $sucursal, $formaPago, $vendedor, $fecIni, $fecFin)
    {
        $data = DB::select("select c.cfa_fecha,
                                    (case when pc.tipo_servicio = 'M' then pv.pve_nombre else a.alm_nombre end) as sucursal,
                                    pv.pve_nombre,
                                    concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                    concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', pv.pve_numero, ' - ',  c.cfa_numero) as num_factura,
                                    tp.tpr_nombre as tipo_producto,
                                    concat(p.pro_codigo, ' - ', p.pro_nombre) as producto,
                                    v.dfac_costoprecio as venta,
                                    coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) as gex,
                                    v.dfac_costoprecio + coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) as subtotal,
                                    v.dfac_dsc1y2 as descuentos,
                                    v.dfac_iva as impuesto,
                                    v.dfac_costoprecio + coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) - v.dfac_dsc1y2 + v.dfac_iva as venta_neta,
                                    v.intereses as interes,
                                    v.dfac_costoprecio + coalesce((select cg.valor_gex from cgex cg where cg.id_dfactura = d.dfac_id),0) * (case when v.dfac_costoprecio < 0 then -1 else 1 end) - v.dfac_dsc1y2 + v.dfac_iva + v.intereses as total,
                                    po.pol_nombre as forma_pago,
                                    concat(ftp.factpa_plazo, ' ', ftp.factpa_tiempo) as plazo
                            from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                            join v_dfacturacompleto_almespa v on d.cfa_id = v.cfa_id and d.dfac_id = v.dfac_id
                                            left outer join fac_tipo_pago ftp on c.cfa_id = ftp.cfa_id
                                            join producto p on d.pro_id = p.pro_id and pro_inventario = true
                                            join tipo_producto tp on p.tpr_id = tp.tpr_id
                                            left outer join politica po on c.pol_id = po.pol_id
                                            join puntoventa pv on c.pve_id = pv.pve_id
                                            join ctipocom t on c.cti_id = t.cti_id
                                            join almacen a on pv.alm_id = a.alm_id
                                            join empleado e on c.vnd_id = e.emp_id
                                            join entidad en on e.ent_id = en.ent_id
                                            left outer join gex.producto_config pc on p.pro_id = pc.pro_id
                            where cast(c.cfa_fecha as date) between '" . $fecIni . "' and '" . $fecFin . "'
                                    and (tp.tpr_id = " . $tipoProd . " or 0 = " . $tipoProd . ")
                                    and tp.tpr_nombre like 'MOTO %'
                                    and (p.pro_id = " . $producto . " or 0 = " . $producto . ")
                                    and ((case when pc.tipo_servicio = 'M' then pv.pve_id else a.alm_id end) = " . $sucursal . " or 0 = " . $sucursal . ")
                                    and (po.pol_id = " . $formaPago . " or 0 = " . $formaPago . ")
                                    and (e.emp_id= " . $vendedor . " or 0 = " . $vendedor . ")
                                    and exists (select 1 from gex.rel_linea_gex rlg where rlg.tpr_id = tp.tpr_id)
                            order by c.cfa_fecha, sucursal, vendedor, num_factura");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}
