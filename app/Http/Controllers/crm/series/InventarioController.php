<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\series\Inventario;
use App\Models\crm\series\InventarioDet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select c.numero,
                                    TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    b.bod_nombre as bodega,
                                    (case c.estado when 'A' then 'PENDIENTE' when 'D' then 'DESACTIVO' when 'P' then 'PROCESADO' end) as estado,
                                    c.responsable
                            from gex.cinventario c join bodega b on c.bod_id = b.bod_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos()
    {
        $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta, pc.tipo_servicio as tipo from producto p left outer join gex.producto_config pc  on p.pro_id = pc.pro_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function bodegas()
    {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b order by presenta");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byInventario($numero)
    {
        $data = Inventario::with('detalle')->get()->where('numero', $numero)->first();
        $data['bodega'] = DB::selectOne("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $data['bod_id']);

        foreach ($data['detalle'] as $p) {
            $producto = DB::selectOne("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p['pro_id']);
            $p['producto'] = $producto->presenta;
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Inventario Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Inventario no existe', []));
        }
    }

    public function byInventarioProc($numero)
    {
        $data = Inventario::get()->where('numero', $numero)->first();
        $data['bodega'] = DB::selectOne("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $data['bod_id']);
        $data['detalle'] = DB::select("select d.*
                                        from gex.cinventario c join gex.dinventario d on c.numero = d.numero
                                        where c.numero = " . $numero . " and not exists (select 1 from gex.stock_serie ss where ss.pro_id = d.pro_id and ss.serie = d.serie and ss.bod_id = c.bod_id and ss.tipo = d.tipo)");

        foreach ($data['detalle'] as $p) {
            $producto = DB::selectOne("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p->pro_id);
            $p->producto = $producto->presenta;
            $p->procesado = 'S';
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Inventario Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Inventario no existe', []));
        }
    }

    public function grabaInventario(Request $request)
    {
        try {
            $numero = 0;

            if ($request->input('numero') == null) {
                $numero = Inventario::max('numero') + 1;
            } else {
                $numero = $request->input('numero');
            }

            DB::transaction(function() use ($request, $numero){
                date_default_timezone_set("America/Guayaquil");                
                
                $fecha_crea = null;
                $fecha_modifica = null;

                if ($request->input('numero') == null) {
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }

                $fecha = $request->input('fecha');
                $estado = $request->input('estado');
                $bod_id = $request->input('bod_id');
                $responsable = $request->input('responsable');
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cinventario')->updateOrInsert(
                    ['numero' => $numero],
                    [
                    'numero' => $numero,
                    'fecha' => $fecha,
                    'estado' => $estado,
                    'bod_id' => $bod_id,
                    'responsable' => $responsable,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('detalle');                
                
                foreach ($detalle as $d) {
                    DB::table('gex.dinventario')->updateOrInsert(
                        [
                            'numero' => $numero,
                            'linea' => $d['linea'],
                        ],
                        [
                            'numero' => $numero,
                            'linea' => $d['linea'],
                            'pro_id' => $d['pro_id'],
                            'serie' => $d['serie'],
                            'tipo' => $d['tipo'],
                            'procesado' => $d['procesado'],
                        ]);
                }

                if ($estado == 'P'){
                    foreach ($detalle as $d) {
                        $existe = DB::selectOne("select 1 from gex.producto_serie ps where ps.pro_id = " . $d['pro_id'] . " and ps.serie = '" . $d['serie'] . "' and ps.tipo = '" . $d['tipo'] . "'");

                        if ($existe == null){
                            DB::table('gex.producto_serie')->updateOrInsert(
                                [
                                    'pro_id' => $d['pro_id'],
                                    'serie' => $d['serie'],
                                    'tipo' => $d['tipo'],
                                ],
                                [
                                    'pro_id' => $d['pro_id'],
                                    'serie' => $d['serie'],
                                    'tipo' => $d['tipo'],
                                ]);
                        }

                        DB::table('gex.stock_serie')->where('pro_id',$d['pro_id'])->where('serie',$d['serie'])->where('tipo',$d['tipo'])->delete();

                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                                'tipo' => $d['tipo'],
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                                'tipo' => $d['tipo'],
                            ]);
                    }
                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Inventario grabado con exito', $numero));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function imprimeInventario($numero) {
        $data = DB::select("select TO_CHAR(c.fecha_crea::date, 'dd/mm/yyyy') as fechaEmision,
                                    TO_CHAR(c.fecha_crea::time, 'hh:ss') as horaEmision,
                                    c.numero,
                                    TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    b.bod_nombre,
                                    c.responsable,
                                    p.pro_id,
                                    p.pro_codigo,
                                    p.pro_nombre,
                                    cast(sum((case d.tipo when 'N' then 1 else 0.5 end)) as integer) as cantidad,
                                    (select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.dinventario d2 where d2.numero = c.numero) as cantidadTotal,
                                    (case c.estado when 'A' then 'PENDIENTE' when 'D' then 'DESACTIVO' when 'P' then 'PROCESADO' end) as estado,
                                    min(d.linea) as linea
                            from gex.cinventario c join bodega b on c.bod_id = b.bod_id
                                                join gex.dinventario d on c.numero = d.numero
                                                join producto p on d.pro_id = p.pro_id
                            where c.numero = " . $numero . "
                            group by c.fecha_crea, c.numero, c.fecha, b.bod_nombre, p.pro_id, p.pro_codigo, p.pro_nombre, c.estado
                            order by linea");

        foreach ($data as $i) {
            if ($i->pro_id != null){
                $i->series = DB::select("select d.serie, (case d.tipo when 'C' then 'COMPRESOR' when 'E' then 'EVAPORADOR' end) as tipo,
                                                (case when d.procesado = 'S' then 'PROCESADO' end) as estadoProd
                                        from gex.dinventario d
                                        where d.numero = " . $i->numero . " and d.pro_id = " . $i->pro_id);
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', '', $data));
    }

    public function anulaInventario($numero)
    {
        try {
            DB::transaction(function() use ($numero){
                date_default_timezone_set("America/Guayaquil");
                
                $data = Inventario::with('detalle')->get()->where('numero', $numero)->first();

                $fecha_crea = $data['fecha_crea'];
                $fecha_modifica = date("Y-m-d h:i:s");
                $estado = 'D';
                $bod_id = $data['bod_id'];
    
                $usuario_crea = $data['usuario_crea'];
                $usuario_modifica = $data['usuario_modifica'];
    
                DB::table('gex.cinventario')->updateOrInsert(
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
            
            return response()->json(RespuestaApi::returnResultado('success', 'Inventario anulado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaInventario($numero) {
        try {
            DB::transaction(function() use ($numero){
                $dato = Inventario::with('detalle')->get()->where('numero', $numero)->first();

                DB::table('gex.dinventario')->where('numero',$numero)->delete();
                DB::table('gex.cinventario')->where('numero',$numero)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Inventario eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}