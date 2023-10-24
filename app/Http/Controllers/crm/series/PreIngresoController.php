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
                                    case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end as estado,
                                    c.cmo_id
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
        if ($data['cmo_id'] == null) {
            $data['doc_rela'] = null;    
        } else {
            $rela = DB::select("select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cmo_numero) as numero
                                        from cmovinv c join puntoventa p on c.pve_id = p.pve_id
                                                    join ctipocom t on c.cti_id = t.cti_id
                                        where c.cmo_id = " . $data['cmo_id'])[0];
            
            $data['doc_rela'] = $rela->numero;
        }

        foreach ($data['detalle'] as $p) {
            $producto = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p['pro_id'])[0];
            $p['producto'] = $producto->presenta;
        }

        $data['impresion'] = DB::select("select TO_CHAR(c.fecha_crea::date, 'dd/mm/yyyy') as fechaEmision,
                                        TO_CHAR(c.fecha_crea::time, 'hh:ss') as horaEmision,
                                        c.numero,
                                        TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                        c.guia_remision,
                                        b.bod_nombre,
                                        concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as proveedor,
                                        p.pro_id,
                                        p.pro_codigo,
                                        p.pro_nombre,
                                        count(*) as cantidad,
                                        (select count(*) from gex.dpreingreso d2 where d2.numero = c.numero) as cantidadTotal,
                                        (case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end) as estado,
                                        min(d.linea) as linea
                                from gex.cpreingreso c join gex.dpreingreso d on c.numero = d.numero
                                                    join bodega b on c.bod_id = b.bod_id
                                                    join cliente l on c.cli_id = l.cli_id
                                                    join entidad e on l.ent_id = e.ent_id
                                                    join producto p on d.pro_id = p.pro_id
                                where c.numero = " . $data['numero'] .
                               "group by c.fecha_crea, c.numero, c.fecha, c.guia_remision, b.bod_nombre, e.ent_identificacion, e.ent_nombres, e.ent_apellidos, p.pro_id, p.pro_codigo, p.pro_nombre, c.estado
                                order by linea");

        foreach ($data['impresion'] as $i) {
            $i->series = DB::select("select d.serie
                                    from gex.dpreingreso d
                                    where d.numero = " . $i->numero . " and d.pro_id = " . $i->pro_id);
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
                $cmo_id = null;
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
                    'cmo_id' => $cmo_id,
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

                foreach ($data['detalle'] as $d) {
                    DB::table('gex.producto_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->delete();
                }
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

            return response()->json(RespuestaApi::returnResultado('success', 'Preingreso eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function cargaIngresos() {
        $data = DB::select("select c.cmo_id,
                                    concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cmo_numero) as numero,
                                    TO_CHAR(c.cmo_fecha::date, 'dd/mm/yyyy') as fecha,
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as proveedor
                            from cmovinv c join puntoventa p on c.pve_id = p.pve_id
                                        join ctipocom t on c.cti_id = t.cti_id
                                        join cliente l on c.cli_id = l.cli_id
                                        join entidad e on l.ent_id = e.ent_id
                            where c.cti_id = 6 and c.cmo_fecha >= '2023-06-01'
                                    and (select sum(d.dmo_cantidad) from dmovinv d where d.cmo_id = c.cmo_id) > (select count(*)
                                                                                                                from gex.dpreingreso d2 join gex.cpreingreso c2 on d2.numero = c2.numero
                                                                                                                where c2.cmo_id = c.cmo_id)");

        foreach ($data as $d) {
            $d->detalle = DB::select("select pro_id, dmo_cantidad - (select count(*) from gex.cpreingreso c join gex.dpreingreso d2 on c.numero = d2.numero
                                                                     where c.cmo_id = d.cmo_id and d2.pro_id = d.pro_id) as saldo
                                      from dmovinv d where cmo_id = " . $d->cmo_id);
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function cargaPreingresos() {
        $data = PreIngreso::with('detalle')->get()->where('cmo_id', null)->where('estado', 'A');
        
        foreach ($data as $d) {
            $d['bodega'] = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $d['bod_id'])[0];
            $d['cliente'] = DB::select("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                                (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as presenta
                                        from cliente c join entidad e on c.ent_id = e.ent_id
                                        where c.cli_tipocli = 2 and c.cli_id = " . $d['cli_id'])[0];
            $d['fechaPresenta'] = date_format(date_create($d['fecha']),'d/m/Y');

            foreach ($d['detalle'] as $p) {
                $producto = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p['pro_id'])[0];
                $p['producto'] = $producto->presenta;
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function cargaRelaciones() {
        $data = DB::select("select c.numero, concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  ci.cmo_numero) as relacionado,
                                    TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    c.guia_remision, b.bod_nombre,
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as proveedor,
                                    (select count(*) from gex.dpreingreso d where d.numero = c.numero) as mov,
                                    (select count(*) from gex.dpreingreso d join gex.stock_serie ss on d.pro_id = ss.pro_id and d.serie = ss.serie where d.numero = c.numero and ss.bod_id = c.bod_id) as stock
                            from gex.cpreingreso c join bodega b on c.bod_id = b.bod_id
                                                join cliente l on c.cli_id = l.cli_id
                                                join entidad e on l.ent_id = e.ent_id
                                                join cmovinv ci on c.cmo_id = ci.cmo_id
                                                join puntoventa p on ci.pve_id = p.pve_id
                                                join ctipocom t on ci.cti_id = t.cti_id
                            where c.cmo_id is not null and c.estado = 'A'");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function relacionaPreIngreso(Request $request)
    {
        try {
            $preIngresos = $request->all();

            DB::transaction(function() use ($preIngresos){
                date_default_timezone_set("America/Guayaquil");
                
                foreach ($preIngresos as $p) {
                    $numero = $p['numero'];
                    $fecha_crea = $p['fecha_crea'];
                    $fecha_modifica = date("Y-m-d h:i:s");
                    $cmo_id = $p['cmo_id'];
        
                    $usuario_crea = $p['usuario_crea'];
                    $usuario_modifica = $p['usuario_modifica'];

                    DB::table('gex.cpreingreso')->updateOrInsert(
                        ['numero' => $numero],
                        [
                        'numero' => $numero,
                        'cmo_id' => $cmo_id,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                        ]);

                    foreach ($p['detalle'] as $d) {
                        $pro_id = $d['pro_id'];
                        $serie = $d['serie'];
                        $bod_id = $p['bod_id'];

                        DB::table('gex.stock_serie')->insert(
                            [
                                'pro_id' => $pro_id,
                                'serie' => $serie,
                                'bod_id' => $bod_id,
                            ]);
                    }
                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Preingresos relacionados con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function quitaRelacionPI($numero, $usuario)
    {
        try {
            DB::transaction(function() use ($numero, $usuario){
                date_default_timezone_set("America/Guayaquil");

                $data = PreIngreso::with('detalle')->get()->where('numero', $numero)->first();

                $numero = $data['numero'];
                $fecha_crea = $data['fecha_crea'];
                $fecha_modifica = date("Y-m-d h:i:s");
                $cmo_id = null;
        
                $usuario_crea = $data['usuario_crea'];
                $usuario_modifica = $usuario;

                DB::table('gex.cpreingreso')->updateOrInsert(
                    ['numero' => $numero],
                    [
                    'numero' => $numero,
                    'cmo_id' => $cmo_id,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);

                foreach ($data['detalle'] as $d) {
                    DB::table('gex.stock_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->delete();
                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se quitó la relacion del preingreso con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}