<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentasTotalesGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function almacenes($usuario)
    {
        $data = DB::select("select a.alm_id, a.alm_nombre
                            from almacen a join gex.rel_usuario_almacenes rua on a.alm_id = rua.alm_id
                                           join crm.users u on rua.usu_id = u.usu_id
                            where u.id = " . $usuario . "
                            union all
                            select a.alm_id, a.alm_nombre
                            from almacen a join puntoventa p on a.alm_id = p.alm_id
                                           join usuario u on p.pve_id = u.pve_id
                                           join crm.users u1 on u.usu_id = u1.usu_id
                            where u1.id = " . $usuario . " and not exists (select 1 from gex.rel_usuario_almacenes rua where rua.usu_id = u.usu_id)
                            order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function vendedores()
    {
        $data = DB::select("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id order by en.ent_nombres");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function VentasTotalesGex($almacen, $usuario, $vendedor, $fecIni, $fecFin)
    {
        $data = DB::select("select alm_id, alm_nombre, emp_id, vendedor,
                                        sum(venta_bruta) as vta_bruto, sum(iva) as iva, sum(venta_neta) as vta_neta,
                                        sum(devolucion_bruta) as dev_bruta, sum(iva_devolucion) as iva_dev, sum(devolucion_neta) as dev_neta,
                                        sum(venta_bruta - devolucion_bruta) as total_bruto, sum(iva - iva_devolucion) as total_iva, sum(venta_neta - devolucion_neta) as total_neto
                                from(
                                select a.alm_id, a.alm_nombre,
                                                e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                                sum(d.dfac_valortotal) as venta_bruta,
                                                round(sum((d.dfac_valortotal * i.imp_porcentaje) / 100),2) as iva,
                                                sum(d.dfac_valortotal + round((d.dfac_valortotal * i.imp_porcentaje) / 100,2)) as venta_neta,
                                                1.11 - 1.11 as devolucion_bruta,
                                                1.11 - 1.11 as iva_devolucion,
                                                1.11 - 1.11 as devolucion_neta
                                from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                                                join puntoventa p on c.pve_id = p.pve_id
                                                                join almacen a on p.alm_id = a.alm_id
                                                                join empleado e on c.vnd_id = e.emp_id
                                                                join entidad en on e.ent_id = en.ent_id
                                                                join impuesto i on c.imp_id = i.imp_id
                                where (a.alm_id = " . $almacen . " or exists (select 1
                                                                                from gex.rel_usuario_almacenes rua join crm.users u on rua.usu_id = u.usu_id
                                                                                where u.id = " . $usuario . " and 0 = " . $almacen . " and rua.alm_id = a.alm_id)) and
                                                (e.emp_id = " . $vendedor . " or 0 = " . $vendedor . ") and
                                                c.cfa_fecha between '" . $fecIni . "' and '" . $fecFin . "'
                                                and d.prod_gex = true
                                group by a.alm_id, a.alm_nombre, e.emp_id, en.ent_nombres, en.ent_apellidos
                                union all
                                select a.alm_id, a.alm_nombre,
                                                e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                                0,
                                                0,
                                                0,
                                                sum(d.dnc_valortotal),
                                                round(sum((d.dnc_valortotal * i.imp_porcentaje) / 100),2),
                                                sum(d.dnc_valortotal + round((d.dnc_valortotal * i.imp_porcentaje) / 100,2))
                                from cnotacre c join dnotacre d on c.cnc_id = d.cnc_id
                                                                join puntoventa p on c.pve_id = p.pve_id
                                                                join almacen a on p.alm_id = a.alm_id
                                                                join empleado e on c.emp_id = e.emp_id
                                                                join entidad en on e.ent_id = en.ent_id
                                                                join impuesto i on c.imp_id = i.imp_id
                                where (a.alm_id = " . $almacen . " or exists (select 1
                                                                                from gex.rel_usuario_almacenes rua join crm.users u on rua.usu_id = u.usu_id
                                                                                where u.id = " . $usuario . " and 0 = " . $almacen . " and rua.alm_id = a.alm_id)) and
                                                (e.emp_id = " . $vendedor . " or 0 = " . $vendedor . ") and
                                                c.cnc_fecha between '" . $fecIni . "' and '" . $fecFin . "'
                                                and d.id_dfactura_gex is not null
                                group by a.alm_id, a.alm_nombre, e.emp_id, en.ent_nombres, en.ent_apellidos) as tabla
                                group by alm_id, alm_nombre, emp_id, vendedor
                                order by alm_nombre, vendedor");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function VentasTotalesGexAlmacen($almacen, $usuario, $fecIni, $fecFin)
    {
        $data = DB::select("select alm_id, alm_nombre,
                                        sum(venta_bruta) as vta_bruto, sum(iva) as iva, sum(venta_neta) as vta_neta,
                                        sum(devolucion_bruta) as dev_bruta, sum(iva_devolucion) as iva_dev, sum(devolucion_neta) as dev_neta,
                                        sum(venta_bruta - devolucion_bruta) as total_bruto, sum(iva - iva_devolucion) as total_iva, sum(venta_neta - devolucion_neta) as total_neto
                                from(
                                select a.alm_id, a.alm_nombre,
                                                sum(d.dfac_valortotal) as venta_bruta,
                                                round(sum((d.dfac_valortotal * i.imp_porcentaje) / 100),2) as iva,
                                                sum(d.dfac_valortotal + round((d.dfac_valortotal * i.imp_porcentaje) / 100,2)) as venta_neta,
                                                1.11 - 1.11 as devolucion_bruta,
                                                1.11 - 1.11 as iva_devolucion,
                                                1.11 - 1.11 as devolucion_neta
                                from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                                                join puntoventa p on c.pve_id = p.pve_id
                                                                join almacen a on p.alm_id = a.alm_id
                                                                join impuesto i on c.imp_id = i.imp_id
                                where (a.alm_id = " . $almacen . " or exists (select 1
                                                                                from gex.rel_usuario_almacenes rua join crm.users u on rua.usu_id = u.usu_id
                                                                                where u.id = " . $usuario . " and 0 = " . $almacen . " and rua.alm_id = a.alm_id)) and
                                                c.cfa_fecha between '" . $fecIni . "' and '" . $fecFin . "'
                                                and d.prod_gex = true
                                group by a.alm_id, a.alm_nombre
                                union all
                                select a.alm_id, a.alm_nombre,
                                                0,
                                                0,
                                                0,
                                                sum(d.dnc_valortotal),
                                                round(sum((d.dnc_valortotal * i.imp_porcentaje) / 100),2),
                                                sum(d.dnc_valortotal + round((d.dnc_valortotal * i.imp_porcentaje) / 100,2))
                                from cnotacre c join dnotacre d on c.cnc_id = d.cnc_id
                                                                join puntoventa p on c.pve_id = p.pve_id
                                                                join almacen a on p.alm_id = a.alm_id
                                                                join impuesto i on c.imp_id = i.imp_id
                                where (a.alm_id = " . $almacen . " or exists (select 1
                                                                                from gex.rel_usuario_almacenes rua join crm.users u on rua.usu_id = u.usu_id
                                                                                where u.id = " . $usuario . " and 0 = " . $almacen . " and rua.alm_id = a.alm_id)) and
                                                c.cnc_fecha between '" . $fecIni . "' and '" . $fecFin . "'
                                                and d.id_dfactura_gex is not null
                                group by a.alm_id, a.alm_nombre) as tabla
                                group by alm_id, alm_nombre
                                order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}