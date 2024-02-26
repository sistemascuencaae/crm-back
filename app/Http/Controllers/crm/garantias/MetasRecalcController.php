<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\MetasRecalc;
use App\Models\crm\garantias\MetasDetRecalc;
use App\Models\crm\garantias\Metas;
use App\Models\crm\garantias\MetasDet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetasRecalcController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select m.metare_id, m.alm_id, a.alm_nombre as descripcion,
                                    concat((case m.mes when 1 then 'ENERO' when 2 then 'FEBRERO' when 3 then 'MARZO'
                                                    when 4 then 'ABRIL' when 5 then 'MAYO' when 6 then 'JUNIO'
                                                    when 7 then 'JULIO' when 8 then 'AGOSTO' when 9 then 'SEPTIEMBRE'
                                                    when 10 then 'OCTUBRE' when 11 then 'NOVIEMBRE' when 12 then 'DICIEMBRE'end),'-',m.anio)as periodo,
                                    m.monto_meta, m.porc_meta_gex, m.monto_meta_gex
                            from gex.cmeta_recal m join almacen a on m.alm_id = a.alm_id
                            order by a.alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function tomaInformacion($almacen,$mes,$anio)
    {
        $ultimo = MetasRecalc::where('alm_id', $almacen)->where('mes', $mes)->where('anio', $anio)->max('metare_id');

        if ($ultimo) {
            $data = MetasRecalc::get()->where('metare_id', $ultimo)->where('alm_id', $almacen)->first();
            $data['vendedores'] = MetasDetRecalc::get()->where('metare_id', $ultimo)->where('alm_id', $almacen);
            $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);
        } else {
            $data = Metas::get()->where('alm_id', $almacen)->first();
            $data['vendedores'] = MetasDet::get()->where('alm_id', $almacen);
            $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);
        }

        foreach ($data['vendedores'] as $m) {
            $vendedor = DB::selectone("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id where e.emp_id = " . $m['emp_id']);
            $m['vendedor'] = $vendedor->vendedor;
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byMetaRecal($metaRecal, $almacen)
    {
        $data = MetasRecalc::get()->where('metare_id', $metaRecal)->where('alm_id', $almacen)->first();
        $data['config'] = $data = DB::select("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor, rav.tipo_empleado
                                              from empleado e join entidad en on e.ent_id = en.ent_id
                                                              join gex.rel_almacen_vendedor rav on e.emp_id = rav.emp_id 
                                              where rav.alm_id = " . $almacen . " and rav.tipo_empleado <> 'J'
                                              order by vendedor");
        $data['vendedores'] = MetasDetRecalc::get()->where('metare_id', $metaRecal)->where('alm_id', $almacen);
        $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);

        $ultimo = MetasRecalc::where('alm_id', $almacen)->where('mes', $data['mes'])->where('anio', $data['anio'])->max('metare_id');

        if ($ultimo) {
            $data['anteriorRecal'] = MetasRecalc::get()->where('metare_id', $ultimo)->where('alm_id', $almacen)->first();
            $data->anterior['vendedores'] = MetasDetRecalc::get()->where('metare_id', $ultimo)->where('alm_id', $almacen);
            $data->anterior['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);
        } else {
            $data['anterior'] = Metas::get()->where('alm_id', $almacen)->first();
            $data->anterior['vendedores'] = MetasDet::get()->where('alm_id', $almacen);
            $data->anterior['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);
        }

        foreach ($data->anterior['vendedores'] as $v) {
            $vendedor = DB::selectone("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id where e.emp_id = " . $v['emp_id']);
            $v['vendedor'] = $vendedor->vendedor;
        }

        foreach ($data['vendedores'] as $m) {
            $vendedor = DB::selectone("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id where e.emp_id = " . $m['emp_id']);
            $m['vendedor'] = $vendedor->vendedor;
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Configuracion Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La configuracion no existe', []));
        }
    }

    public function grabaMetaRecal(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $metare_id = 0;
                $alm_id = $request->input('alm_id');
                $mes = $request->input('mes');
                $anio = $request->input('anio');
                $monto_meta = $request->input('monto_meta');
                $porc_meta_gex = $request->input('porc_meta_gex');
                $monto_meta_gex = $request->input('monto_meta_gex');
                $fecha_crea = null;
                $fecha_modifica = null;
    
                if ($request->input('metare_id') == null) {
                    $metare_id = MetasRecalc::where('alm_id', $alm_id)->max('meta_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $metare_id = $request->input('metare_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cmeta_recal')->updateOrInsert(
                    [
                        'metare_id' => $meta_id,
                        'alm_id' => $alm_id,
                    ],                        
                    [
                        'metare_id' => $meta_id,
                        'alm_id' => $alm_id,
                        'mes' => $mes,
                        'anio' => $anio,
                        'monto_meta' => $monto_meta,
                        'porc_meta_gex' => $porc_meta_gex,
                        'monto_meta_gex' => $monto_meta_gex,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('vendedores');
                
                DB::table('gex.dmeta_recal')->where('metare_id',$metare_id)->where('alm_id',$alm_id)->delete();

                foreach ($detalle as $d) {
                    DB::table('gex.dmeta_recal')->updateOrInsert(
                        [
                            'metare_id' => $metare_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                        ],
                        [
                            'metare_id' => $metare_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                            'dias_perm_vac' => $d['dias_perm_vac'],
                            'porc_meta' => $d['porc_meta'],
                            'monto_meta' => $d['monto_meta'],
                            'porc_meta_gex' => $d['porc_meta_gex'],
                            'monto_meta_gex' => $d['monto_meta_gex'],
                        ]);

                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Meta grabada con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaMetaRecal($metaRecal_id,$alm_id) {
        try {
            DB::transaction(function() use ($metaRecal_id,$alm_id){
                DB::table('gex.dmeta_recal')->where('metare_id',$metaRecal_id)->where('alm_id',$alm_id)->delete();
                DB::table('gex.cmeta_recal')->where('metare_id',$metaRecal_id)->where('alm_id',$alm_id)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Meta eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}