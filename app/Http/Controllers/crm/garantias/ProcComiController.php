<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ProcComi;
use App\Models\crm\garantias\ProcComiDet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcComiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select cc.procomi_id, cc.cumpli_id, cc.alm_id,
                                    a.alm_nombre,
                                    concat((case cm.mes when 1 then 'ENERO' when 2 then 'FEBRERO' when 3 then 'MARZO'
                                                                                    when 4 then 'ABRIL' when 5 then 'MAYO' when 6 then 'JUNIO'
                                                                                    when 7 then 'JULIO' when 8 then 'AGOSTO' when 9 then 'SEPTIEMBRE'
                                                                                    when 10 then 'OCTUBRE' when 11 then 'NOVIEMBRE' when 12 then 'DICIEMBRE'end),'-',cm.anio)as periodo,
                                    sum(dc.valor_vendedor) as comi_vend,
                                    sum(dc.valor_jfa) as comi_jfa,
                                    sum(dc.valor_jfz) as comi_jfz,
                                    sum(dc.valor_jfv) as comi_jfv,
                                    sum(dc.valor_jfg) as comi_jfg
                            from gex.cproc_comisiones cc join gex.cproc_metas cm on cc.cumpli_id  = cm.cumpli_id and cc.alm_id = cm.alm_id
                                                        join gex.dproc_comisiones dc on cc.procomi_id = dc.procomi_id and cc.cumpli_id = dc.cumpli_id and cc.alm_id = dc.alm_id
                                                        join almacen a on cc.alm_id = a.alm_id
                            group by cc.procomi_id, cc.cumpli_id, cc.alm_id, a.alm_nombre, cm.mes, cm.anio");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function cumplimientos($almacen)
    {
        $data = DB::select("select cm.cumpli_id, concat((case cm.mes when 1 then 'ENERO' when 2 then 'FEBRERO' when 3 then 'MARZO'
                                                                when 4 then 'ABRIL' when 5 then 'MAYO' when 6 then 'JUNIO'
                                                                when 7 then 'JULIO' when 8 then 'AGOSTO' when 9 then 'SEPTIEMBRE'
                                                                when 10 then 'OCTUBRE' when 11 then 'NOVIEMBRE' when 12 then 'DICIEMBRE'end),'-',cm.anio)as periodo
                            from gex.cproc_metas cm
                            where cm.alm_id = " . $almacen . " and not exists (select 1 from gex.cproc_comisiones cc where cc.cumpli_id = cm.cumpli_id and cc.alm_id = cm.alm_id)
                            order by cm.mes, cm.anio");
        

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function procesaComisiones($cumplimiento, $almacen)
    {
        $data = DB::select("select dm.cumpli_id,
                                    dm.emp_id,
                                    concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor,
                                    dm.venta_gex,
                                    dm.cumplimiento,
                                    dm.cumplimiento_gex,
                                    coalesce((select c.porc_vendedor
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0) as porc_vend,
                                    coalesce((select c.porc_jfa
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0) as porc_jfa,
                                    coalesce((select c.porc_jfz
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0) as porc_jfz,
                                    coalesce((select c.porc_jfv
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0) as porc_jfv,
                                    coalesce((select c.porc_jfg
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0) as porc_jfg,
                                    round(coalesce((select dm.venta_gex * (c.porc_vendedor / 100)
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0),2) as val_vend,
                                    round(coalesce((select dm.venta_gex * (c.porc_jfa / 100)
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0),2) as val_jfa,
                                    round(coalesce((select dm.venta_gex * (c.porc_jfz / 100)
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0),2) as val_jfz,
                                    round(coalesce((select dm.venta_gex * (c.porc_jfv / 100)
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0),2) as val_jfv,
                                    round(coalesce((select dm.venta_gex * (c.porc_jfg / 100)
                                            from gex.comisiones c
                                            where (dm.cumplimiento between c.cumpli_prod_ini and c.cumpli_prod_fin) and
                                                    (dm.cumplimiento_gex between c.cumpli_gex_ini and c.cumpli_gex_fin)),0),2) as val_jfg
                            from gex.dproc_metas dm join empleado e on dm.emp_id = e.emp_id
                                                    join entidad en on e.ent_id = en.ent_id
                            where dm.cumpli_id = " . $cumplimiento . " and dm.alm_id = " . $almacen . " and dm.tipo_emp = 'V'");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byProcComi($comi, $cumpli, $almacen)
    {
        $data = ProcComi::get()->where('procomi_id', $comi)->where('cumpli_id', $cumpli)->where('alm_id', $almacen)->first();
        $data['vendedores'] = ProcComiDet::get()->where('procomi_id', $comi)->where('cumpli_id', $cumpli)->where('alm_id', $almacen);
        $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $data['alm_id']);
        $data['cumplimiento'] = DB::selectone("select cm. cumpli_id, concat((case cm.mes when 1 then 'ENERO' when 2 then 'FEBRERO' when 3 then 'MARZO'
                                                    when 4 then 'ABRIL' when 5 then 'MAYO' when 6 then 'JUNIO'
                                                    when 7 then 'JULIO' when 8 then 'AGOSTO' when 9 then 'SEPTIEMBRE'
                                                    when 10 then 'OCTUBRE' when 11 then 'NOVIEMBRE' when 12 then 'DICIEMBRE'end),'-',cm.anio)as periodo
                                               from gex.cproc_metas cm
                                               where cm.cumpli_id = " . $cumpli . " and cm.alm_id = " . $almacen);

        foreach ($data['vendedores'] as $m) {
            $vendedor = DB::selectone("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id where e.emp_id = " . $m['emp_id']);
            $m['vendedor'] = $vendedor->vendedor;
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Comisiones Encontradas', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Proceso de Comision no existe', []));
        }
    }

    public function grabaProcComi(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $procomi_id = 0;
                $cumpli_id = $request->input('cumpli_id');
                $alm_id = $request->input('alm_id');
                $fecha_crea = null;
                $fecha_modifica = null;
    
                if ($request->input('procomi_id') == null) {
                    $procomi_id = ProcComi::where('cumpli_id', $cumpli_id)->where('alm_id', $alm_id)->max('procomi_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $procomi_id = $request->input('procomi_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cproc_comisiones')->updateOrInsert(
                    [
                        'procomi_id' => $procomi_id,
                        'cumpli_id' => $cumpli_id,
                        'alm_id' => $alm_id,
                    ],                        
                    [
                        'procomi_id' => $procomi_id,
                        'cumpli_id' => $cumpli_id,
                        'alm_id' => $alm_id,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('vendedores');
                
                DB::table('gex.dproc_comisiones')->where('procomi_id',$procomi_id)->where('cumpli_id',$cumpli_id)->where('alm_id',$alm_id)->delete();

                foreach ($detalle as $d) {
                    DB::table('gex.dproc_comisiones')->updateOrInsert(
                        [
                            'procomi_id' => $procomi_id,
                            'cumpli_id' => $cumpli_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                        ],
                        [
                            'procomi_id' => $procomi_id,
                            'cumpli_id' => $cumpli_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                            'venta_gex' => $d['venta_gex'],
                            'cumplimiento' => $d['cumplimiento'],
                            'cumplimiento_gex' => $d['cumplimiento_gex'],
                            'porc_vendedor' => $d['porc_vendedor'],
                            'porc_jfa' => $d['porc_jfa'],
                            'porc_jfz' => $d['porc_jfz'],
                            'porc_jfv' => $d['porc_jfv'],
                            'porc_jfg' => $d['porc_jfg'],
                            'valor_vendedor' => $d['valor_vendedor'],
                            'valor_jfa' => $d['valor_jfa'],
                            'valor_jfz' => $d['valor_jfz'],
                            'valor_jfv' => $d['valor_jfv'],
                            'valor_jfg' => $d['valor_jfg'],
                        ]);

                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Proceso de Comisiones grabado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaProcComi($comi,$cumpli,$almacen) {
        try {
            DB::transaction(function() use ($comi,$cumpli,$almacen){
                DB::table('gex.dproc_comisiones')->where('procomi_id',$comi)->where('cumpli_id',$cumpli)->where('alm_id',$almacen)->delete();
                DB::table('gex.cproc_comisiones')->where('procomi_id',$comi)->where('cumpli_id',$cumpli)->where('alm_id',$almacen)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Proceso de Comisiones eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}