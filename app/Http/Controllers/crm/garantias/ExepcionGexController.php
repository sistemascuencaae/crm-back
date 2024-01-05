<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ExepcionGex;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExepcionGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select eg.exce_id, p.pro_nombre as descripcion,
                                    concat(p1.pro_nombre, ' - ', pc.meses_garantia, ' meses - ', pc.porc_gex, '%') as gex,
                                    concat(TO_CHAR(eg.fecha_ini::date, 'dd/mm/yyyy'), ' - ', TO_CHAR(eg.fecha_fin::date, 'dd/mm/yyyy')) as periodo,
                                    eg.porc_gex as porcentaje
                            from producto p join gex.excepciones_gex eg on p.pro_id = eg.pro_id
                                            join gex.producto_config pc on eg.config_id = pc.config_id
                                            join producto p1 on pc.pro_id = p1.pro_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos()
    {
        $data = DB::select("select p.pro_id, p.tpr_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta
                            from producto p join gex.rel_linea_gex rlg on p.tpr_id = rlg.tpr_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function gexRelacionado($tipo_producto)
    {
        $data = DB::select("select pc.config_id, concat(p.pro_nombre, ' - ', pc.meses_garantia, ' meses - ', pc.porc_gex, '%') as presenta
                            from producto p join gex.producto_config pc on p.pro_id = pc.pro_id
                                            join gex.rel_linea_gex rlg on pc.config_id = rlg.config_id and rlg.tpr_id = " . $tipo_producto);

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byExcep($excep)
    {
        $data = ExepcionGex::select()->where('exce_id', $excep)->first();
        $data['producto'] = DB::selectone("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $data['pro_id']);
        $data['configuracion'] = DB::selectone("select pc.config_id, concat(p.pro_nombre, ' - ', pc.meses_garantia, ' meses - ', pc.porc_gex, '%') as presenta
                                                from gex.producto_config pc join producto p on pc.pro_id = p.pro_id
                                                where pc.config_id = " . $data['config_id']);

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Configuracion Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La configuracion no existe', []));
        }
    }

    public function grabaExep(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
            
                $exce_id = 0;
                $fecha_crea = null;
                $fecha_modifica = null;

                if ($request->input('exce_id') == null) {
                    $exce_id = ExepcionGex::max('exce_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $exce_id = $request->input('exce_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }

                $pro_id = $request->input('pro_id');
                $config_id = $request->input('config_id');
                $porc_gex = $request->input('porc_gex');
                $fecha_ini = $request->input('fecha_ini');
                $fecha_fin = $request->input('fecha_fin');
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');

                DB::table('gex.excepciones_gex')->updateOrInsert(
                    ['exce_id' => $exce_id],
                    [
                    'exce_id' => $exce_id,
                    'pro_id' => $pro_id,
                    'config_id' => $config_id,
                    'porc_gex' => $porc_gex,
                    'fecha_ini' => $fecha_ini,
                    'fecha_fin' => $fecha_fin,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Excepción grabada con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaExep($excep) {
        try {
            DB::transaction(function() use ($excep){
                DB::table('gex.excepciones_gex')->where('exce_id',$excep)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Excepción eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}