<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\series\Despacho;
use App\Models\crm\series\DespachoDet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DespachoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado($bodega)
    {
        $data = DB::select("select c.cmo_id,
                                    concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cmo_numero) as numero,
                                    TO_CHAR(c.cmo_fecha::date, 'dd/mm/yyyy') as fecha,
                                    concat(b.bod_nombre,' / ',b1.bod_nombre) as proveedor,
                                    'INV' as op,
                                    c.cmo_id as indice,
                                    c.bod_id, c.bod_id_fin,
                                    null as cli_id,
                                    (select sum(d.dmo_cantidad) from dmovinv d where d.cmo_id = c.cmo_id) as cantidad,
                                    (select count(*) from gex.ddespacho d2 join gex.cdespacho c2 on d2.numero = c2.numero where c2.cmo_id = c.cmo_id) as despachado
                            from cmovinv c join puntoventa p on c.pve_id = p.pve_id
                                        join ctipocom t on c.cti_id = t.cti_id
                                        join bodega b on c.bod_id = b.bod_id
                                        join bodega b1 on c.bod_id_fin = b1.bod_id
                            where c.cti_id in (select r.cti_id from gex.doc_presenta r where r.opcion = 'DES') and c.cmo_fecha >= '2023-06-01'
                                    and c.bod_id = " . $bodega . "
                                    and (not exists (select 1 from gex.cdespacho c1 where c.cmo_id = c1.cmo_id) or
                                        (select sum(d.dmo_cantidad) from dmovinv d where d.cmo_id = c.cmo_id) > (case when exists (select 1 from gex.cdespacho c1 where c.cmo_id = c1.cmo_id) and
                                                                                                                            not exists (select 1 from gex.ddespacho d2 join gex.cdespacho c2 on d2.numero = c2.numero
                                                                                                                                        where c2.cmo_id = c.cmo_id) then (select sum(d.dmo_cantidad) from dmovinv d where d.cmo_id = c.cmo_id)
                                                                                                                else (select count(*)
                                                                                                                        from gex.ddespacho d2 join gex.cdespacho c2 on d2.numero = c2.numero
                                                                                                                        where c2.cmo_id = c.cmo_id) end))
                            union
                            select c.cfa_id,
                                    concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as numero,
                                    TO_CHAR(c.cfa_fecha::date, 'dd/mm/yyyy') as fecha,
                                    concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as proveedor,
                                    'VTA' as op,
                                    c.cfa_id as indice,
                                    p.bod_id, null,
                                    c.cli_id,
                                    (select sum(d.dfac_cantidad) from dfactura d where d.cfa_id = c.cfa_id) as cantidad,
                                    (select count(*) from gex.ddespacho d2 join gex.cdespacho c2 on d2.numero = c2.numero where c2.cfa_id = c.cfa_id) as despachado
                            from cfactura c join puntoventa p on c.pve_id = p.pve_id
                                        join ctipocom t on c.cti_id = t.cti_id
                                        join cliente l on c.cli_id = l.cli_id
                                        join entidad e on l.ent_id = e.ent_id
                            where c.cti_id in (select r.cti_id from gex.doc_presenta r where r.opcion = 'DES') and c.cfa_fecha >= '2023-06-01'
                                    and p.bod_id = " . $bodega . "
                                    and (not exists (select 1 from gex.cdespacho c1 where c1.cfa_id = c.cfa_id) or
                                        (select sum(d.dfac_cantidad) from dfactura d where d.cfa_id = c.cfa_id) > (select count(*)
                                                                                                                from gex.ddespacho d2 join gex.cdespacho c2 on d2.numero = c2.numero
                                                                                                                where c2.cfa_id = c.cfa_id))
                            order by fecha, numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function listadoDespachos()
    {
        $data = DB::select("select c.numero, TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    b.bod_nombre as bodega,
                                    (case when c.cmo_id is null then (select concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end))
                                                                    from cfactura c1 join cliente l on c1.cli_id = l.cli_id
                                                                                    join entidad e on l.ent_id = e.ent_id
                                                                    where c1.cfa_id = c.cfa_id) else (select b1.bod_nombre
                                                                                                        from cmovinv c1 join bodega b1 on c1.bod_id_fin = b1.bod_id
                                                                                                        where c1.cmo_id = c.cmo_id) end) as nombre,
                                    (case when c.cmo_id is null then (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cfa_numero)
                                                                    from cfactura c1 join puntoventa p on c1.pve_id = p.pve_id
                                                                                        join ctipocom t on c1.cti_id = t.cti_id
                                                                    where c1.cfa_id = c.cfa_id) else (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cmo_numero)
                                                                                                        from cmovinv c1 join puntoventa p on c1.pve_id = p.pve_id
                                                                                                                        join ctipocom t on c1.cti_id = t.cti_id
                                                                                                        where c1.cmo_id = c.cmo_id) end) as doc_rela,
                                    case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end as estado,
                                    c.cmo_id,
                                    c.cfa_id,
                                    (select c1.bod_id_fin from cmovinv c1 where c1.cmo_id = c.cmo_id) as bod_id_fin
                            from gex.cdespacho c join bodega b on c.bod_id = b.bod_id
                            order by c.numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function productos($id, $tipo)
    {
        if ($tipo == 'INV') {
            $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p join dmovinv d on p.pro_id = d.pro_id where cmo_id = " . $id);
        } else {
            $data = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p join dfactura d on p.pro_id = d.pro_id where cfa_id = " . $id);
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function bodegas()
    {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b order by presenta");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function clientes($tipo,$id)
    {
        if ($tipo == 'INV') {
            $data = DB::select("select b.bod_id as cli_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $id)[0];
        } else {
            $data = DB::select("select c.cli_id, concat(e.ent_identificacion, ' - ',
                                        (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as presenta
                                from cliente c join entidad e on c.ent_id = e.ent_id
                                where c.cli_id = " . $id)[0];
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function bodegaUsuario($usuario) {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b join puntoventa p on b.bod_id = p.bod_id
                            where p.pve_id = (select (case when us.pve_id is null then u.pve_id else us.pve_id end) as ptoVta from crm.users u left outer join usuario us on u.usu_id  = us.usu_id
                                            where id = " . $usuario . ")")[0];

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byDespacho($numero)
    {
        $data = Despacho::with('detalle')->get()->where('numero', $numero)->first();
        $data['bodega'] = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b where b.bod_id = " . $data['bod_id'])[0];
        
        if ($data['cmo_id'] == null) {
            $rela = DB::select("select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as numero
                                from cfactura c join puntoventa p on c.pve_id = p.pve_id
                                                join ctipocom t on c.cti_id = t.cti_id
                                where c.cfa_id = " . $data['cfa_id'])[0];
        
            $data['doc_rela'] = $rela->numero;

            $data['cliente'] = DB::select("select c1.cli_id, concat(e.ent_identificacion, ' - ',
                                                            (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as presenta
                                            from cfactura c1 join cliente l on c1.cli_id = l.cli_id
                                                            join entidad e on l.ent_id = e.ent_id
                                            where c1.cfa_id = " . $data['cfa_id'])[0];
        } else {
            $rela = DB::select("select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cmo_numero) as numero
                                        from cmovinv c join puntoventa p on c.pve_id = p.pve_id
                                                    join ctipocom t on c.cti_id = t.cti_id
                                        where c.cmo_id = " . $data['cmo_id'])[0];
            
            $data['doc_rela'] = $rela->numero;

            $data['cliente'] = DB::select("select b.bod_id as cli_id, b.bod_nombre as presenta
                                            from cmovinv c join bodega b on c.bod_id_fin = b.bod_id
                                            where c.cmo_id = " . $data['cmo_id'])[0];
        }

        foreach ($data['detalle'] as $p) {
            $producto = DB::select("select p.pro_id, concat(p.pro_codigo, ' - ', p.pro_nombre) as presenta from producto p where p.pro_id = " . $p['pro_id'])[0];
            $p['producto'] = $producto->presenta;
        }

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Despacho Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Despacho no existe', []));
        }
    }

    public function grabaDespacho(Request $request)
    {
        try {
            $numero = 0;

            if ($request->input('numero') == null) {
                $numero = Despacho::max('numero') + 1;
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
                $bod_id_fin = $request->input('bod_id_dest');
                $cmo_id = $request->input('cmo_id');
                $cfa_id = $request->input('cfa_id');
    
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');
    
                DB::table('gex.cdespacho')->updateOrInsert(
                    ['numero' => $numero],
                    [
                    'numero' => $numero,
                    'fecha' => $fecha,
                    'estado' => $estado,
                    'bod_id' => $bod_id,
                    'cmo_id' => $cmo_id,
                    'cfa_id' => $cfa_id,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            
                $detalle = $request->input('detalle');

                foreach ($detalle as $d) {
                    DB::table('gex.ddespacho')->updateOrInsert(
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

                    if ($bod_id_fin == null) {
                        DB::table('gex.stock_serie')->where('pro_id',$d['pro_id'])->where('serie',$d['serie'])->where('bod_id',$bod_id)->delete();
                    } else {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id_fin,
                            ]);
                    }
                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Despacho grabado con exito', $numero));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function imprimeDespacho($numero) {
        $data = DB::select("select TO_CHAR(c.fecha_crea::date, 'dd/mm/yyyy') as fechaEmision,
                                    TO_CHAR(c.fecha_crea::time, 'hh:ss') as horaEmision,
                                    c.numero,
                                    TO_CHAR(c.fecha::date, 'dd/mm/yyyy') as fecha,
                                    b.bod_nombre,
                                    (case when c.cmo_id is null then 'Cliente: ' else 'Bodega Destino: ' end) as etiqueta,
                                    (case when c.cmo_id is null then (select concat(e.ent_identificacion, ' - ', (case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end))
                                                                    from cfactura c1 join cliente l on c1.cli_id = l.cli_id
                                                                                    join entidad e on l.ent_id = e.ent_id
                                                                    where c1.cfa_id = c.cfa_id) else (select b1.bod_nombre
                                                                                                        from cmovinv c1 join bodega b1 on c1.bod_id_fin = b1.bod_id
                                                                                                        where c1.cmo_id = c.cmo_id) end) as nombre,
                                    (case when c.cmo_id is null then 'Factura: ' else 'Traspaso: ' end) as etiquetaDR,
                                    (case when c.cmo_id is null then (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cfa_numero)
                                                                    from cfactura c1 join puntoventa p on c1.pve_id = p.pve_id
                                                                                        join ctipocom t on c1.cti_id = t.cti_id
                                                                    where c1.cfa_id = c.cfa_id) else (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cmo_numero)
                                                                                                        from cmovinv c1 join puntoventa p on c1.pve_id = p.pve_id
                                                                                                                        join ctipocom t on c1.cti_id = t.cti_id
                                                                                                        where c1.cmo_id = c.cmo_id) end) as doc_rela,
                                    p.pro_id,
                                    p.pro_codigo,
                                    p.pro_nombre,
                                    count(d.pro_id) as cantidad,
                                    (select count(*) from gex.ddespacho d2 where d2.numero = c.numero) as cantidadTotal,
                                    (case when c.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end) as estado,
                                    min(d.linea) as linea
                            from gex.cdespacho c join bodega b on c.bod_id = b.bod_id
                                                left outer join gex.ddespacho d on c.numero = d.numero
                                                left outer join producto p on d.pro_id = p.pro_id
                            where c.numero = " . $numero . "
                            group by c.fecha_crea, c.numero, c.fecha, b.bod_nombre, p.pro_id, p.pro_codigo, p.pro_nombre, c.estado
                            order by linea");

        foreach ($data as $i) {
            if ($i->pro_id != null){
                $i->series = DB::select("select d.serie
                                    from gex.ddespacho d
                                    where d.numero = " . $i->numero . " and d.pro_id = " . $i->pro_id);
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', '', $data));
    }

    public function anulaDespacho($numero,$bodDest)
    {
        try {
            DB::transaction(function() use ($numero,$bodDest){
                date_default_timezone_set("America/Guayaquil");
                
                $data = Despacho::with('detalle')->get()->where('numero', $numero)->first();

                $fecha_crea = $data['fecha_crea'];
                $fecha_modifica = date("Y-m-d h:i:s");
                $estado = 'D';
                $bod_id = $data['bod_id'];
    
                $usuario_crea = $data['usuario_crea'];
                $usuario_modifica = $data['usuario_modifica'];
    
                DB::table('gex.cdespacho')->updateOrInsert(
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
                    if ($bodDest == null) {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ]);
                    } else {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bodDest,
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ]);
                    }
                }
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Despacho anulado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaDespacho($numero,$bodDest) {
        try {
            DB::transaction(function() use ($numero,$bodDest){
                $dato = Despacho::with('detalle')->get()->where('numero', $numero)->first();
                $bod_id = $dato['bod_id'];
                
                $data = $dato['detalle'];

                DB::table('gex.ddespacho')->where('numero',$numero)->delete();
                DB::table('gex.cdespacho')->where('numero',$numero)->delete();

                foreach ($data as $d) {
                    if ($bodDest == 'null') {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ]);
                    } else {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bodDest,
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                            ]);
                    }
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Despacho eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function cargaDetalleMovimiento($id, $tipo) {
        if ($tipo == 'INV') {
            $data = DB::select("select pro_id, dmo_cantidad - (select count(*) from gex.cdespacho c join gex.ddespacho d2 on c.numero = d2.numero
                                                                where c.cmo_id = d.cmo_id and d2.pro_id = d.pro_id) as saldo
                                from dmovinv d where cmo_id = " . $id);
        } else {
            $data = DB::select("select pro_id, dfac_cantidad - (select count(*) from gex.cdespacho c join gex.ddespacho d2 on c.numero = d2.numero
                                                                where c.cfa_id = d.cfa_id and d2.pro_id = d.pro_id) as saldo
                                from dfactura d where cfa_id = " . $id);
        }

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}