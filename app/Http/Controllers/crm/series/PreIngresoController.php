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
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when c2.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as proveedor,
                                    case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end as estado,
                                    (select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.dpreingreso d2 where d2.numero = c.numero) as preingresado,
                                    c.cmo_id,
                                    c.cfa_id
                            from gex.cpreingreso c join cliente c2 on c.cli_id = c2.cli_id
                                                join entidad e on c2.ent_id  = e.ent_id
                            order by c.numero");

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
    
    public function clientes()
    {
        $data = DB::select("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                    (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when c.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as presenta
                            from cliente c join entidad e on c.ent_id = e.ent_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function validaSerie($producto, $serie, $tipo)
    {
        $data = DB::selectOne("select * from gex.producto_serie ps where ps.pro_id = " . $producto . " and ps.serie = '" . $serie . "' and ps.tipo = '" . $tipo . "'");
        
        if($data) {
            return response()->json(RespuestaApi::returnResultado('error', 'La serie ingresada ya existe para el producto', []));
        }else{
            return response()->json(RespuestaApi::returnResultado('success', '200', []));
        }
    }

    public function byPreIngreso($numero)
    {
        $data = PreIngreso::with('detalle')->get()->where('numero', $numero)->first();
        $data['bodega'] = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $data['bod_id'])[0];
        $data['cliente'] = DB::select("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                                (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when c.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as presenta
                                        from cliente c join entidad e on c.ent_id = e.ent_id
                                        where c.cli_id = " . $data['cli_id'])[0];
        if ($data['cmo_id'] == null) {
            if ($data['cfa_id'] == null) {
                $data['doc_rela'] = null;
            } else {
                $rela = DB::select("select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as numero
                                        from cfactura c join puntoventa p on c.pve_id = p.pve_id
                                                        join ctipocom t on c.cti_id = t.cti_id
                                        where c.cfa_id = " . $data['cfa_id'])[0];
            
                $data['doc_rela'] = $rela->numero;
            }
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
                                        concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when l.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as proveedor,
                                        p.pro_id,
                                        p.pro_codigo,
                                        p.pro_nombre,
                                        cast(sum((case d.tipo when 'N' then 1 else 0.5 end)) as integer) as cantidad,
                                        (select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.dpreingreso d2 where d2.numero = c.numero) as cantidadTotal,
                                        (case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end) as estado,
                                        min(d.linea) as linea,
                                        (case when c.cmo_id is null then 'Nota/Crédito: ' else 'Ingreso: ' end) as etiquetaDR,
                                        (case when c.cmo_id is null then (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cfa_numero)
                                                                        from cfactura c1 join puntoventa p on c1.pve_id = p.pve_id
                                                                                            join ctipocom t on c1.cti_id = t.cti_id
                                                                        where c1.cfa_id = c.cfa_id) else (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cmo_numero)
                                                                                                            from cmovinv c1 join puntoventa p on c1.pve_id = p.pve_id
                                                                                                                            join ctipocom t on c1.cti_id = t.cti_id
                                                                                                            where c1.cmo_id = c.cmo_id) end) as doc_rela
                                from gex.cpreingreso c join gex.dpreingreso d on c.numero = d.numero
                                                    join bodega b on c.bod_id = b.bod_id
                                                    join cliente l on c.cli_id = l.cli_id
                                                    join entidad e on l.ent_id = e.ent_id
                                                    join producto p on d.pro_id = p.pro_id
                                where c.numero = " . $data['numero'] .
                               "group by c.fecha_crea, c.numero, c.fecha, c.guia_remision, b.bod_nombre, e.ent_identificacion, e.ent_nombres, e.ent_apellidos, l.cli_tipocli, p.pro_id, p.pro_codigo, p.pro_nombre, c.estado
                                order by linea");

        foreach ($data['impresion'] as $i) {
            $i->series = DB::select("select d.serie, (case d.tipo when 'C' then 'COMPRESOR' when 'E' then 'EVAPORADOR' end) as tipo
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
                $cfa_id = null;
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
                    'cfa_id' => $cfa_id,
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
                            'tipo' => $d['tipo'],
                        ],
                        [
                            'pro_id' => $d['pro_id'],
                            'serie' => $d['serie'],
                            'tipo' => $d['tipo'],
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
                            'tipo' => $d['tipo'],
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

                DB::table('gex.dpreingreso')->where('numero',$numero)->delete();

                foreach ($data['detalle'] as $d) {
                    DB::table('gex.producto_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->where('tipo', $d['tipo'])->delete();
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
                    DB::table('gex.producto_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->where('tipo', $d['tipo'])->delete();
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
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when l.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as proveedor,
                                    'INV' as op,
                                    (select cast(sum(d.dmo_cantidad) as integer) from dmovinv d where d.cmo_id = c.cmo_id) as cantidad,
                                    coalesce((select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer)
                                            from gex.dpreingreso d2 join gex.cpreingreso c2 on d2.numero = c2.numero
                                            where c2.cmo_id = c.cmo_id),0) as relacionado
                            from cmovinv c join puntoventa p on c.pve_id = p.pve_id
                                        join ctipocom t on c.cti_id = t.cti_id
                                        join cliente l on c.cli_id = l.cli_id
                                        join entidad e on l.ent_id = e.ent_id
                            where c.cti_id in (select r.cti_id from gex.doc_presenta r where r.opcion = 'PRI') and c.cmo_fecha >= '2024-01-01'
                                    and (select sum(d.dmo_cantidad) from dmovinv d where d.cmo_id = c.cmo_id) > coalesce((select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer)
                                                                                                                        from gex.dpreingreso d2 join gex.cpreingreso c2 on d2.numero = c2.numero
                                                                                                                        where c2.cmo_id = c.cmo_id),0)
                            union
                            select c.cfa_id,
                                    concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as numero,
                                    TO_CHAR(c.cfa_fecha::date, 'dd/mm/yyyy') as fecha,
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when l.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as proveedor,
                                    'VTA' as op,
                                    (select cast(sum(d.dfac_cantidad) as integer) from dfactura d where d.cfa_id = c.cfa_id) as cantidad,
                                    coalesce((select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer)
                                            from gex.dpreingreso d2 join gex.cpreingreso c2 on d2.numero = c2.numero
                                            where c2.cfa_id = c.cfa_id),0) as relacionado
                            from cfactura c join puntoventa p on c.pve_id = p.pve_id
                                        join ctipocom t on c.cti_id = t.cti_id
                                        join cliente l on c.cli_id = l.cli_id
                                        join entidad e on l.ent_id = e.ent_id
                            where c.cti_id in (select r.cti_id from gex.doc_presenta r where r.opcion = 'PRI') and c.cfa_fecha >= '2024-01-01'
                                    and (select sum(d.dfac_cantidad) from dfactura d where d.cfa_id = c.cfa_id) > coalesce((select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer)
                                                                                                                            from gex.dpreingreso d2 join gex.cpreingreso c2 on d2.numero = c2.numero
                                                                                                                            where c2.cfa_id = c.cfa_id),0)
                            order by fecha, numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function cargaDetalleIngreso($id, $tipo) {
        if ($tipo == 'INV') {
            $data = DB::select("select pro_id, dmo_cantidad - coalesce((select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.cpreingreso c join gex.dpreingreso d2 on c.numero = d2.numero
                                                                where c.cmo_id = d.cmo_id and d2.pro_id = d.pro_id),0) as saldo
                                from dmovinv d where cmo_id = " . $id);
        } else {
            $data = DB::select("select pro_id, dfac_cantidad - coalesce((select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.cpreingreso c join gex.dpreingreso d2 on c.numero = d2.numero
                                                                where c.cfa_id = d.cfa_id and d2.pro_id = d.pro_id),0) as saldo
                                from dfactura d where cfa_id = " . $id);
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function cargaPreingresos() {
        $data = PreIngreso::with('detalle')->where('cmo_id', null)->where('estado', 'A')->get();
        
        foreach ($data as $d) {
            $d['bodega'] = DB::selectone("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $d['bod_id']);
            $d['cliente'] = DB::selectone("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                                   (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when c.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as presenta
                                           from cliente c join entidad e on c.ent_id = e.ent_id
                                           where c.cli_id = " . $d['cli_id']);
            $d['fechaPresenta'] = date_format(date_create($d['fecha']),'d/m/Y');
            $d['preingresado'] = DB::selectone("select cast(sum((case d2.tipo when 'N' then 1 else 0.5 end)) as integer) as valor from gex.dpreingreso d2 where d2.numero = " . $d['numero'])->valor;

            foreach ($d['detalle'] as $p) {
                $producto = DB::selectone("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p['pro_id']);
                $p['producto'] = $producto->presenta;
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function cargaRelaciones() {
        $data = DB::select("select c.numero, (case when ci.cmo_numero is null then concat(t1.cti_sigla,' - ', p1.alm_id, ' - ', p1.pve_numero, ' - ',  cf.cfa_numero) else concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  ci.cmo_numero) end) as relacionado,
                                    TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    c.guia_remision, b.bod_nombre,
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end), ' - ', (case when l.cli_tipocli = 1 then 'CLIENTE' else 'PROVEEDOR' end)) as proveedor,
                                    (select cast(sum((case d.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.dpreingreso d where d.numero = c.numero) as mov,
                                    (select cast(sum((case d.tipo when 'N' then 1 else 0.5 end)) as integer) from gex.dpreingreso d join gex.stock_serie ss on d.pro_id = ss.pro_id and d.serie = ss.serie where d.numero = c.numero and ss.bod_id = c.bod_id and ss.tipo = d.tipo) as stock
                            from gex.cpreingreso c join bodega b on c.bod_id = b.bod_id
                                                join cliente l on c.cli_id = l.cli_id
                                                join entidad e on l.ent_id = e.ent_id
                                                left outer join cmovinv ci on c.cmo_id = ci.cmo_id
                                                left outer join cfactura cf on c.cfa_id = cf.cfa_id
                                                left outer join puntoventa p on ci.pve_id = p.pve_id
                                                left outer join puntoventa p1 on cf.pve_id = p1.pve_id
                                                left outer join ctipocom t on ci.cti_id = t.cti_id
                                                left outer join ctipocom t1 on cf.cti_id = t1.cti_id
                            where (c.cmo_id is not null or c.cfa_id is not null) and c.estado = 'A'");

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
                    $cfa_id = $p['cfa_id'];
        
                    $usuario_crea = $p['usuario_crea'];
                    $usuario_modifica = $p['usuario_modifica'];

                    DB::table('gex.cpreingreso')->updateOrInsert(
                        ['numero' => $numero],
                        [
                        'numero' => $numero,
                        'cmo_id' => $cmo_id,
                        'cfa_id' => $cfa_id,
                        'usuario_crea' => $usuario_crea,
                        'fecha_crea' => $fecha_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_modifica' => $fecha_modifica,
                        ]);

                    foreach ($p['detalle'] as $d) {
                        $pro_id = $d['pro_id'];
                        $serie = $d['serie'];
                        $bod_id = $p['bod_id'];
                        $tipo = $d['tipo'];


                        DB::table('gex.stock_serie')->insert(
                            [
                                'pro_id' => $pro_id,
                                'serie' => $serie,
                                'bod_id' => $bod_id,
                                'tipo' => $tipo,
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
                $cfa_id = null;
        
                $usuario_crea = $data['usuario_crea'];
                $usuario_modifica = $usuario;

                DB::table('gex.cpreingreso')->updateOrInsert(
                    ['numero' => $numero],
                    [
                    'numero' => $numero,
                    'cmo_id' => $cmo_id,
                    'cfa_id' => $cfa_id,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);

                foreach ($data['detalle'] as $d) {
                    DB::table('gex.stock_serie')->where('pro_id', $d['pro_id'])->where('serie', $d['serie'])->where('tipo', $d['tipo'])->delete();
                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se quitó la relacion del preingreso con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}