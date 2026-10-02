<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\RelacionAlmacenVendedorGex;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelacionAlamcenVendedorGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select rav.alm_id, rav.emp_id, a.alm_nombre, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor, (case rav.tipo_empleado when 'J' then 'JEFE ALMACEN' else 'VENDEDOR' end) as tipo
                            from gex.rel_almacen_vendedor rav join almacen a on rav.alm_id = a.alm_id
                                                              join empleado e on rav.emp_id = e.emp_id
                                                              join entidad en on e.ent_id = en.ent_id
                            order by a.alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function vendedores()
    {
        $data = DB::select("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id order by en.ent_nombres");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byRelaAlmVen($almacen, $vendedor)
    {
        $data = RelacionAlmacenVendedorGex::select()->where('alm_id',$almacen)->where('emp_id',$vendedor)->first();
        $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);
        $data['vendedor'] = DB::selectone("select e.emp_id, concat(en.ent_nombres, ' ', en.ent_apellidos) as vendedor from empleado e join entidad en on e.ent_id = en.ent_id where e.emp_id = " . $vendedor);

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Relación Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La relación no existe', []));
        }
    }

    public function grabaRelaAlmVen(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $alm_id = $request->input('alm_id');
                $emp_id = $request->input('emp_id');
                $tipo = $request->input('tipo_empleado');
                $fecha_crea = null;
                $fecha_modifica = null;
    
                if ($request->input('modifica') == 'N') {
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.rel_almacen_vendedor')->updateOrInsert(
                    [
                        'alm_id' => $alm_id,
                        'emp_id' => $emp_id,
                    ],
                    [
                        'alm_id' => $alm_id,
                        'emp_id' => $emp_id,
                        'tipo_empleado' => $tipo,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                    ]);
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Relación grabada con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaRelaAlmVen($almacen, $vendedor) {
        try {
            DB::transaction(function() use ($almacen, $vendedor){
                DB::table('gex.rel_almacen_vendedor')->where('alm_id',$almacen)->where('emp_id',$vendedor)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Relación eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}