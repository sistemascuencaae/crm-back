<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\series\PreIngreso;
use App\Models\crm\series\PreIngresoDet;
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
                            from gex.cpreingreso c join cliente c2 on c.cli_id = c2.cli_id
                                                join entidad e on c2.ent_id  = e.ent_id
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
        $data = DB::select("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                    (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as presenta
                            from cliente c join entidad e on c.ent_id = e.ent_id
                            where c.cli_tipocli = 2");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byPreIngreso($numero)
    {
        $data = PreIngreso::with('detalle')->get()->where('numero', $numero)->first();
        $data['bodega'] = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $data['bod_id'])[0];
        $data['cliente'] = DB::select("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                                (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as presenta
                                        from cliente c join entidad e on c.ent_id = e.ent_id
                                        where c.cli_tipocli = 2 and c.cli_id = " . $data['cli_id'])[0];

        foreach ($data['detalle'] as $p) {
            $producto = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p['pro_id'])[0];
            foreach ($producto as $valor) {
                $p['producto'] = $valor;
            }
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'PreIngreso Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Preingreso no existe', []));
        }
    }

    public function grabaPreIngreso(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
                
                $numero = 0;
                $fecha_crea = null;
                $fecha_modifica = null;

                if ($request->input('numero') == null) {
                    $numero = PreIngreso::max('numero') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $numero = $request->input('numero');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }

                $fecha = $request->input('fecha');
                $estado = $request->input('estado');
                $bod_id = $request->input('bod_id');
                $guia_remision = $request->input('guia_remision');
                $cli_id = $request->input('cli_id');
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cpreingreso')->updateOrInsert(
                    ['numero' => $numero],
                    [
                    'numero' => $numero,
                    'fecha' => $fecha,
                    'estado' => $estado,
                    'bod_id' => $bod_id,
                    'guia_remision' => $guia_remision,
                    'cli_id' => $cli_id,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('detalle');
                
                $data = (PreIngreso::with('detalle')->get()->where('numero', $numero)->first())['detalle'];

                DB::table('gex.dpreingreso')->where('numero',$numero)->delete();

                foreach ($data as $d) {
                    DB::table('gex.producto_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->delete();
                }

                foreach ($detalle as $d) {
                    DB::table('gex.producto_serie')->updateOrInsert(
                        [
                            'pro_id' => $d['pro_id'],
                            'serie' => $d['serie'],
                        ],
                        [
                            'pro_id' => $d['pro_id'],
                            'serie' => $d['serie'],
                        ]);

                    DB::table('gex.dpreingreso')->updateOrInsert(
                        [
                            'numero' => $numero,
                            'linea' => $d['linea'],
                        ],
                        [
                            'numero' => $numero,
                            'linea' => $d['linea'],
                            'pro_id' => $d['pro_id'],
                            'serie' => $d['serie'],
                        ]);

                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Preingreso grabado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function anulaPreIngreso($numero)
    {
        try {
            DB::transaction(function() use ($numero){
                date_default_timezone_set("America/Guayaquil");
                
                $data = PreIngreso::with('detalle')->get()->where('numero', $numero)->first();

                $fecha_crea = $data['fecha_crea'];
                $fecha_modifica = date("Y-m-d h:i:s");
                $estado = 'D';
    
                $usuario_crea = $data['usuario_crea'];
                $usuario_modifica = $data['usuario_modifica'];
    
                DB::table('gex.cpreingreso')->updateOrInsert(
                    ['numero' => $numero],
                    [
                    'numero' => $numero,
                    'estado' => $estado,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Preingreso anulado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaPreIngreso($numero) {
        try {
            DB::transaction(function() use ($numero){
                $data = (PreIngreso::with('detalle')->get()->where('numero', $numero)->first())['detalle'];

                DB::table('gex.dpreingreso')->where('numero',$numero)->delete();
                DB::table('gex.cpreingreso')->where('numero',$numero)->delete();

                foreach ($data as $d) {
                    DB::table('gex.producto_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->delete();
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Configuración eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}