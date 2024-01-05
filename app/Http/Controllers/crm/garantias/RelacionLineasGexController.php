<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\RelacionLineasGex;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelacionLineasGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select rlg.config_id, rlg.tpr_id, p.pro_nombre, tp.tpr_nombre
                            from gex.rel_linea_gex rlg join tipo_producto tp on rlg.tpr_id = tp.tpr_id
                                                       join gex.producto_config pc on rlg.config_id = pc.config_id
                                                       join producto p on pc.pro_id = p.pro_id
                            order by tp.tpr_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos()
    {
        $data = DB::select("select pc.config_id, concat(p.pro_codigo, ' - ', p.pro_nombre, ' - ', pc.meses_garantia, ' meses - ', pc.porc_gex, '%') as presenta
                            from producto p join gex.producto_config pc on p.pro_id = pc.pro_id
                            where pc.tipo_servicio = 'G'");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function lineas()
    {
        $data = DB::select("select tp.tpr_id, tp.tpr_nombre from tipo_producto tp");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byRela($linea, $config)
    {
        $data = RelacionLineasGex::select()->where('tpr_id', $linea)->where('config_id',$config)->first();
        $data['linea'] = DB::selectone("select tp.tpr_id, tp.tpr_nombre from tipo_producto tp where tp.tpr_id = " . $linea);
        $data['producto'] = DB::selectone("select pc.config_id, concat(p.pro_codigo, ' - ', p.pro_nombre, ' - ', pc.meses_garantia, ' meses - ', pc.porc_gex, '%') as presenta
                                           from producto p join gex.producto_config pc on p.pro_id = pc.pro_id
                                           where pc.config_id = " . $config);

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Configuracion Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La configuracion no existe', []));
        }
    }

    public function grabaRela(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $tpr_id = $request->input('tpr_id');
                $config_id = $request->input('config_id');
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
    
                DB::table('gex.rel_linea_gex')->updateOrInsert(
                    [
                        'tpr_id' => $tpr_id,
                        'config_id' => $config_id,
                    ],
                    [
                        'tpr_id' => $tpr_id,
                        'config_id' => $config_id,
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

    public function eliminaRela($linea, $config) {
        try {
            DB::transaction(function() use ($linea, $config){
                DB::table('gex.rel_linea_gex')->where('tpr_id',$linea)->where('config_id',$config)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Relación eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}