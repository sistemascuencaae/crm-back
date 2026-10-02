<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ProcMetas;
use App\Models\crm\garantias\ProcMetasDet;
use App\Models\crm\garantias\MetasRecalc;
use App\Models\crm\garantias\Metas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcMetasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select cm.cumpli_id, cm.alm_id,
                                    a.alm_nombre,
                                    concat((case cm.mes when 1 then 'ENERO' when 2 then 'FEBRERO' when 3 then 'MARZO'
                                                        when 4 then 'ABRIL' when 5 then 'MAYO' when 6 then 'JUNIO'
                                                        when 7 then 'JULIO' when 8 then 'AGOSTO' when 9 then 'SEPTIEMBRE'
                                                        when 10 then 'OCTUBRE' when 11 then 'NOVIEMBRE' when 12 then 'DICIEMBRE'end),'-',cm.anio)as periodo,
                                    dm.venta,
                                    dm.venta_gex,
                                    dm.cumplimiento,
                                    dm.cumplimiento_gex
                            from gex.cproc_metas cm join gex.dproc_metas dm on cm.alm_id = dm.alm_id and cm.cumpli_id = dm.cumpli_id and dm.tipo_emp = 'J'
                                                    join almacen a on cm.alm_id = a.alm_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function procesaMetas($almacen, $mes, $anio)
    {
        $proceso = DB::selectone("select count(*) from gex.cproc_metas where alm_id = " . $almacen . "and mes = " . $mes . " and anio = " . $anio);

        if ($proceso->count > 0) {
            return response()->json(RespuestaApi::returnResultado('error', 'No puede procersar un periodo ya procesado.', []));
        }

        $existe = MetasRecalc::where('alm_id', $almacen)->where('mes', $mes)->where('anio', $anio)->max('metare_id');
        
        $tabla = "";
        $tablaD = "";
        $campo = "";
        $config = 0;

        if ($existe) {
            $tabla = "cmeta_recal";
            $tablaD = "dmeta_recal";
            $campo = "metare_id";
            $config = $existe;
        } else {
            $tabla = "cmeta";
            $tablaD = "dmeta";
            $campo = "meta_id";
            $config = Metas::where('alm_id', $almacen)->max('meta_id');
        }

        if (!$config) {
            return response()->json(RespuestaApi::returnResultado('error', 'No existen configuraciones para procesar las metas.', []));
        }

        $data = DB::select("select tabla.alm_id, d.emp_id, vendedor,
                                    round(sum(venta),2) as vta_prod,
                                    round(sum(venta_gex),2) as vta_gex,
                                    d.porc_meta,
                                    d.monto_meta,
                                    d.porc_meta_gex,
                                    d.monto_meta_gex,
                                    round((sum(venta) / d.monto_meta) * 100,2) as cumpli_prod,
                                    round((sum(venta_gex) / d.monto_meta_gex) * 100,2) as cumpli_gex,
                                    'V' as tipo
                            from
                                (select a.alm_id,
                                        e.emp_id,
                                        concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                        sum((case when pc.tipo_servicio = 'G' or pc.tipo_servicio = 'N' then 0 else v.dfac_costoprecio + v.intereses end)) as venta,
                                        sum((case when pc.tipo_servicio = 'G' or pc.tipo_servicio = 'N' then v.dfac_costoprecio + v.intereses else 0 end)) as venta_gex
                                from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                                join v_dfacturacompleto_almespa v on d.cfa_id = v.cfa_id and d.dfac_id = v.dfac_id
                                                join producto p on d.pro_id = p.pro_id
                                                join puntoventa pv on c.pve_id = pv.pve_id
                                                join almacen a on pv.alm_id = a.alm_id
                                                join empleado e on c.vnd_id = e.emp_id
                                                join entidad en on e.ent_id = en.ent_id
                                                left outer join gex.producto_config pc on p.pro_id = pc.pro_id
                                where extract(month from c.cfa_fecha) = " . $mes . " and extract(year from c.cfa_fecha) = " . $anio . "
                                        and a.alm_id = " . $almacen . "
                                group by a.alm_id, e.emp_id, vendedor, pc.tipo_servicio) as tabla join gex." . $tablaD . " d on tabla.emp_id = d.emp_id and d." . $campo . " = " . $config . " and d.alm_id = " . $almacen . "
                            group by tabla.alm_id, d.emp_id, vendedor, d.porc_meta, d.monto_meta, d.porc_meta_gex, d.monto_meta_gex
                            union all
                            select tabla.alm_id, e.emp_id,
                                    concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                    round(sum(venta),2) as vta_prod,
                                    round(sum(venta_gex),2) as vta_gex,
                                    100,
                                    c.monto_meta,
                                    c.porc_meta_gex,
                                    c.monto_meta_gex,
                                    round((sum(venta) / c.monto_meta) * 100,2) as cumpli_prod,
                                    round((sum(venta_gex) / c.monto_meta_gex) * 100,2) as cumpli_gex,
                                    'J'
                            from
                                (select a.alm_id,
                                        sum((case when pc.tipo_servicio = 'G' or pc.tipo_servicio = 'N' then 0 else v.dfac_costoprecio + v.intereses end)) as venta,
                                        sum((case when pc.tipo_servicio = 'G' or pc.tipo_servicio = 'N' then v.dfac_costoprecio + v.intereses else 0 end)) as venta_gex
                                from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                                join v_dfacturacompleto_almespa v on d.cfa_id = v.cfa_id and d.dfac_id = v.dfac_id
                                                join producto p on d.pro_id = p.pro_id
                                                join puntoventa pv on c.pve_id = pv.pve_id
                                                join almacen a on pv.alm_id = a.alm_id
                                                left outer join gex.producto_config pc on p.pro_id = pc.pro_id
                                where extract(month from c.cfa_fecha) = " . $mes . " and extract(year from c.cfa_fecha) = " . $anio . "
                                        and a.alm_id = " . $almacen . "
                                group by a.alm_id, pc.tipo_servicio) as tabla join gex." . $tabla . " c on c." . $campo . " = " . $config . " and c.alm_id = " . $almacen . "
                                                                            join gex.rel_almacen_vendedor rav on rav.alm_id = c.alm_id and rav.tipo_empleado = 'J'
                                                                            join empleado e on rav.emp_id = e.emp_id
                                                                            join entidad en on e.ent_id = en.ent_id
                            group by tabla.alm_id, e.emp_id, vendedor, c.monto_meta, c.porc_meta_gex, c.monto_meta_gex");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byProcMeta($cumpli, $almacen)
    {
        $data = ProcMetas::get()->where('cumpli_id', $cumpli)->where('alm_id', $almacen)->first();
        $data['vendedores'] = ProcMetasDet::get()->where('cumpli_id', $cumpli)->where('alm_id', $almacen);
        $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $data['alm_id']);

        foreach ($data['vendedores'] as $m) {
            $vendedor = DB::selectone("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id where e.emp_id = " . $m['emp_id']);
            $m['vendedor'] = $vendedor->vendedor;
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Proceso de Meta Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Proceso de Meta no existe', []));
        }
    }

    public function grabaProcMeta(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $cumpli_id = 0;
                $alm_id = $request->input('alm_id');
                $mes = $request->input('mes');
                $anio = $request->input('anio');
                $fecha_crea = null;
                $fecha_modifica = null;
    
                if ($request->input('cumpli_id') == null) {
                    $cumpli_id = ProcMetas::where('alm_id', $alm_id)->max('cumpli_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $cumpli_id = $request->input('cumpli_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cproc_metas')->updateOrInsert(
                    [
                        'cumpli_id' => $cumpli_id,
                        'alm_id' => $alm_id,
                    ],                        
                    [
                        'cumpli_id' => $cumpli_id,
                        'alm_id' => $alm_id,
                        'mes' => $mes,
                        'anio' => $anio,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('vendedores');
                
                DB::table('gex.dproc_metas')->where('cumpli_id',$cumpli_id)->where('alm_id',$alm_id)->delete();

                foreach ($detalle as $d) {
                    DB::table('gex.dproc_metas')->updateOrInsert(
                        [
                            'cumpli_id' => $cumpli_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                        ],
                        [
                            'cumpli_id' => $cumpli_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                            'venta' => $d['venta'],
                            'venta_gex' => $d['venta_gex'],
                            'porc_meta' => $d['porc_meta'],
                            'monto_meta' => $d['monto_meta'],
                            'porc_meta_gex' => $d['porc_meta_gex'],
                            'monto_meta_gex' => $d['monto_meta_gex'],
                            'cumplimiento' => $d['cumplimiento'],
                            'cumplimiento_gex' => $d['cumplimiento_gex'],
                            'tipo_emp' => $d['tipo_emp'],
                        ]);

                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Proceso de Metas grabado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaProcMeta($cumpli,$almacen) {
        try {
            DB::transaction(function() use ($cumpli,$almacen){
                DB::table('gex.dproc_metas')->where('cumpli_id',$cumpli)->where('alm_id',$almacen)->delete();
                DB::table('gex.cproc_metas')->where('cumpli_id',$cumpli)->where('alm_id',$almacen)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Proceso de Metas eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}