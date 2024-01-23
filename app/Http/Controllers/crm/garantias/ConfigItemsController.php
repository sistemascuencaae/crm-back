<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ConfigItems;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigItemsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select pc.config_id, p.pro_codigo, p.pro_nombre as descripcion, case pc.tipo_servicio when 'G' then 'GEX' when 'S' then 'SEGURO' when 'P' then 'PRODUCTO' when 'A' then 'PRODUCTO A/C' when 'M' then 'PRODUCTO MOTO' when 'N' then 'GEX MOTO' end as tipo,
                                   pc.porc_gex, pc.meses_garantia, pc.km_garantia, pc.km_factor
                            from producto p join gex.producto_config pc on p.pro_id = pc.pro_id
                            order by p.pro_codigo");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos()
    {
        $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function partes()
    {
        $data = DB::select("select p.parte_id, p.descripcion from gex.partes p");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byConfig($config)
    {
        $data = ConfigItems::with('partes')->get()->where('config_id', $config)->first();
        $data['producto'] = DB::selectone("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $data['pro_id']);

        foreach ($data['partes'] as $p) {
            $parte = DB::selectone("select p.descripcion from gex.partes p where p.parte_id = " . $p['parte_id']);
            $p['parte'] = $parte->descripcion;
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Configuracion Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La configuracion no existe', []));
        }
    }

    public function grabaConfig(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $config_id = 0;
                $pro_id = $request->input('pro_id');
                $tipo_servicio = $request->input('tipo_servicio');
                $porc_gex = $request->input('porc_gex');
                $meses_garantia = $request->input('meses_garantia');
                $fecha_crea = null;
                $fecha_modifica = null;
                $km_garantia = $request->input('km_garantia');
                $km_factor = $request->input('km_factor');
    
                if ($request->input('modifica') == 'N') {
                    $config_id = ConfigItems::max('config_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $config_id = $request->input('config_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.producto_config')->updateOrInsert(
                    ['config_id' => $config_id],
                    [
                    'config_id' => $config_id,
                    'pro_id' => $pro_id,
                    'tipo_servicio' => $tipo_servicio,
                    'porc_gex' => $porc_gex,
                    'meses_garantia' => $meses_garantia,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    'km_garantia' => $km_garantia,
                    'km_factor' => $km_factor,
                    ]);
            
                $detalle = $request->input('partes');
                
                DB::table('gex.producto_partes')->where('config_id',$config_id)->delete();

                foreach ($detalle as $d) {
                    DB::table('gex.producto_partes')->updateOrInsert(
                        [
                            'config_id' => $config_id,
                            'parte_id' => $d['parte_id'],
                        ],
                        [
                            'config_id' => $config_id,
                            'parte_id' => $d['parte_id'],
                            'meses_garantia' => $d['meses_garantia'],
                        ]);

                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Configuración grabada con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaConfig($config) {
        try {
            DB::transaction(function() use ($config){
                DB::table('gex.producto_partes')->where('config_id',$config)->delete();
                DB::table('gex.producto_config')->where('config_id',$config)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Configuración eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
    
    public function validaConfig(Request $request)
    {
        $pro_id = $request->input('pro_id');
        $tipo_servicio = $request->input('tipo_servicio');
        $porc_gex = $request->input('porc_gex');
        $meses_garantia = $request->input('meses_garantia');
        $km_factor = $request->input('km_factor');

        if ($tipo_servicio == 'G' || $tipo_servicio == 'N') {
            $data = ConfigItems::get()->where('pro_id', $pro_id)->where('tipo_servicio', $tipo_servicio)->where('porc_gex', $porc_gex)->where('meses_garantia', $meses_garantia)->where('km_factor', $km_factor)->first();
        } else {
            $data = ConfigItems::get()->where('pro_id', $pro_id)->first();
        }
        
        if ($data){
            return response()->json(RespuestaApi::returnResultado('error', 'Producto ya está configurado', []));
        } else {
            return response()->json(RespuestaApi::returnResultado('success', '', []));
        }
    }
}