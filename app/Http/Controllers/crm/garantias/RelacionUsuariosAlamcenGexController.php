<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\RelacionUsuariosAlmacenGex;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelacionUsuariosAlamcenGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select rua.usu_id, rua.alm_id, concat(u.usu_nombre, ' ', u.usu_apellido) as usuario, a.alm_nombre
                            from gex.rel_usuario_almacenes rua join usuario u on rua.usu_id = u.usu_id
                                                            join almacen a on rua.alm_id = a.alm_id
                            order by usuario, a.alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function usuarios()
    {
        $data = DB::select("select u.usu_id, concat(u.usu_nombre, ' ', u.usu_apellido) as usuario from usuario u where u.usu_activo = true order by u.usu_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byRela($usuario, $almacen)
    {
        $data = RelacionUsuariosAlmacenGex::select()->where('usu_id', $usuario)->where('alm_id',$almacen)->first();
        $data['usuario'] = DB::selectone("select u.usu_id, concat(u.usu_nombre, ' ', u.usu_apellido) as usuario from usuario u where u.usu_id = " . $usuario);
        $data['almacen'] = DB::selectone("select alm_id, alm_nombre from almacen a where a.alm_id = " . $almacen);

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Relación Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La relación no existe', []));
        }
    }

    public function grabaRela(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $usu_id = $request->input('usu_id');
                $alm_id = $request->input('alm_id');
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
    
                DB::table('gex.rel_usuario_almacenes')->updateOrInsert(
                    [
                        'usu_id' => $usu_id,
                        'alm_id' => $alm_id,
                    ],
                    [
                        'usu_id' => $usu_id,
                        'alm_id' => $alm_id,
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

    public function eliminaRela($usuario, $almacen) {
        try {
            DB::transaction(function() use ($usuario, $almacen){
                DB::table('gex.rel_usuario_almacenes')->where('usu_id',$usuario)->where('alm_id',$almacen)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Relación eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}