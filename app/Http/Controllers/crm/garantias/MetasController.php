<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\Metas;
use App\Models\crm\garantias\MetasDet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select m.meta_id, m.alm_id, a.alm_nombre as descripcion, m.monto_meta, m.porc_meta_gex, m.monto_meta_gex
                            from gex.cmeta m join almacen a on m.alm_id = a.alm_id
                            order by a.alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function vendedores($almacen)
    {
        $data = DB::select("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor, rav.tipo_empleado
                            from empleado e join entidad en on e.ent_id = en.ent_id
                                            join gex.rel_almacen_vendedor rav on e.emp_id = rav.emp_id 
                            where rav.alm_id = " . $almacen . " and rav.tipo_empleado <> 'J'
                            order by vendedor");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byMeta($meta, $almacen)
    {
        $data = Metas::get()->where('meta_id', $meta)->where('alm_id', $almacen)->first();
        $data['vendedores'] = MetasDet::get()->where('meta_id', $meta)->where('alm_id', $almacen);
        $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $data['alm_id']);

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

    public function grabaMeta(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $meta_id = 0;
                $alm_id = $request->input('alm_id');
                $monto_meta = $request->input('monto_meta');
                $porc_meta_gex = $request->input('porc_meta_gex');
                $monto_meta_gex = $request->input('monto_meta_gex');
                $fecha_crea = null;
                $fecha_modifica = null;
    
                if ($request->input('meta_id') == null) {
                    $meta_id = Metas::where('alm_id', $alm_id)->max('meta_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $meta_id = $request->input('meta_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cmeta')->updateOrInsert(
                    [
                        'meta_id' => $meta_id,
                        'alm_id' => $alm_id,
                    ],                        
                    [
                        'meta_id' => $meta_id,
                        'alm_id' => $alm_id,
                        'monto_meta' => $monto_meta,
                        'porc_meta_gex' => $porc_meta_gex,
                        'monto_meta_gex' => $monto_meta_gex,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('vendedores');
                
                DB::table('gex.dmeta')->where('meta_id',$meta_id)->where('alm_id',$alm_id)->delete();

                foreach ($detalle as $d) {
                    DB::table('gex.dmeta')->updateOrInsert(
                        [
                            'meta_id' => $meta_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
                        ],
                        [
                            'meta_id' => $meta_id,
                            'alm_id' => $alm_id,
                            'emp_id' => $d['emp_id'],
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

    public function eliminaMeta($meta_id,$alm_id) {
        try {
            DB::transaction(function() use ($meta_id,$alm_id){
                DB::table('gex.dmeta')->where('meta_id',$meta_id)->where('alm_id',$alm_id)->delete();
                DB::table('gex.cmeta')->where('meta_id',$meta_id)->where('alm_id',$alm_id)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Meta eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}