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
        $data = DB::select("select rlg.pro_id, rlg.tpr_id, p.pro_nombre, tp.tpr_nombre
                            from gex.rel_linea_gex rlg join tipo_producto tp on rlg.tpr_id = tp.tpr_id
                                                       join producto p on rlg.pro_id = p.pro_id
                            order by tp.tpr_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos()
    {
        $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta
                            from producto p join gex.producto_config pc on p.pro_id = pc.pro_id
                            where pc.tipo_servicio = 'G'");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function lineas()
    {
        $data = DB::select("select tp.tpr_id, tp.tpr_nombre from tipo_producto tp");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byRela($linea, $producto)
    {
        $data = RelacionLineasGex::select()->where('tpr_id', $linea)->where('pro_id',$producto)->first();
        $data['linea'] = DB::select("select tp.tpr_id, tp.tpr_nombre from tipo_producto tp where tp.tpr_id = " . $linea)[0];
        $data['producto'] = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $producto)[0];

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
                $pro_id = $request->input('pro_id');
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
                        'pro_id' => $pro_id,
                    ],
                    [
                        'tpr_id' => $tpr_id,
                        'pro_id' => $pro_id,
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

    public function eliminaRela($linea, $producto) {
        try {
            DB::transaction(function() use ($linea, $producto){
                DB::table('gex.rel_linea_gex')->where('tpr_id',$linea)->where('pro_id',$producto)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Relación eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}