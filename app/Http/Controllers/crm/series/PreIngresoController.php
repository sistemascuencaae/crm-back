<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\series\PreIngreso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreIngresoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select c.numero, TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha, guia_remision,
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as proveedor,
                                    case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end as estado
                            from gex.cpreingreso c join entidad e on c.cli_id  = e.ent_id
                            order by c.numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos()
    {
        $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function bodegas()
    {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b order by presenta");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function clientes()
    {
        $data = DB::select("select e.ent_id as cli_id, concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as presenta from entidad e");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byPreIngreso($producto)
    {
        $data = ConfigItems::with('partes')->get()->where('pro_id', $producto)->first();
        $data['producto'] = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $producto)[0];

        foreach ($data['partes'] as $p) {
            $parte = DB::select("select p.descripcion from gex.partes p where p.parte_id = " . $p['parte_id'])[0];
            foreach ($parte as $valor) {
                $p['parte'] = $valor;
            }
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Configuracion Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La configuracion no existe', []));
        }
    }

    public function grabaPreIngreso(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $pro_id = $request->input('pro_id');
                $tipo_servicio = $request->input('tipo_servicio');
                $porc_gex = $request->input('porc_gex');
                $meses_garantia = $request->input('meses_garantia');
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
    
                DB::table('gex.producto_config')->updateOrInsert(
                    ['pro_id' => $pro_id],
                    [
                    'pro_id' => $pro_id,
                    'tipo_servicio' => $tipo_servicio,
                    'porc_gex' => $porc_gex,
                    'meses_garantia' => $meses_garantia,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('partes');
                
                DB::table('gex.producto_partes')->where('pro_id',$pro_id)->delete();

                foreach ($detalle as $d) {
                    DB::table('gex.producto_partes')->updateOrInsert(
                        [
                            'pro_id' => $d['pro_id'],
                            'parte_id' => $d['parte_id'],
                        ],
                        [
                            'pro_id' => $d['pro_id'],
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

    public function eliminaPreIngreso($producto) {
        try {
            DB::transaction(function() use ($producto){
                DB::table('gex.producto_partes')->where('pro_id',$producto)->delete();
                DB::table('gex.producto_config')->where('pro_id',$producto)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Configuración eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}