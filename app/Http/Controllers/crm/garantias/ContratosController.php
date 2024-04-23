<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ContratoGex;
use App\Models\crm\garantias\FolioContratos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select cg.nom_almacen,
                                    cg.numero,
                                    cg.factura,
                                    cg.factura_gex,
                                    concat(cg.identificacion, ' - ', cg.nom_cliente) as cliente,
                                    cg.producto,
                                    cg.fecha as fecha,
                                    cg.alm_id
                            from gex.contrato_gex cg
                            order by cg.nom_almacen, cg.numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function almacenes()
    {
        $data = DB::select("select alm_id, alm_nombre from almacen a order by alm_nombre");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function facturas($almacen)
    {
        $data = DB::select("select c.cfa_id, concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as numero
                            from cfactura c join puntoventa p on c.pve_id = p.pve_id
                                            join almacen a on p.alm_id = a.alm_id
                                            join ctipocom t on c.cti_id = t.cti_id
                                            join dfactura d on c.cfa_id = d.cfa_id
                            where p.alm_id = " . $almacen . " and exists (select 1 from gex.cdespacho c1 where c1.cfa_id = c.cfa_id)
                                    and exists (select 1 from cgex g where g.id_dfactura = d.dfac_id)
                                    and not exists (select 1 from gex.contrato_gex cg where cg.cfa_id = d.cfa_id and cg.pro_id = d.pro_id)
                            group by c.cfa_id, t.cti_sigla,a.alm_codigo, p.pve_numero,  c.cfa_numero
                            order by numero");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function datosContrato($factura)
    {
        $data = DB::select("select p.alm_id,
                                    a.alm_nombre as nom_almacen,
                                    concat((case when e.ent_nombres = '' then e.ent_apellidos else concat(e.ent_nombres, ' ', e.ent_apellidos) end)) as nom_cliente,
                                    (case ve.ent_tipo_identificacion when '1' then 'CEDULA' when '2' then 'RUC' else 'PASAPORTE' end) as tipo_identificacion,
                                    ve.ent_identificacion as identificacion,
                                    (select distinct u.ubi_nombre from v_ubicacion u where u.ubi2_id = a.ubi_id) as provincia,
                                    (select distinct u.ubi2_nombre from v_ubicacion u where u.ubi2_id = a.ubi_id) as ciudad,
                                    concat(ve.dir_calle_principal, ' ', ve.dir_numeracion, ' ', ve.dir_calle_secundaria, ' / ', trim(vu.ubi_nombre), ' - ', trim(vu.ubi2_nombre)) as direccion,
                                    (select string_agg(tel_numero, '/')
                                    from (select te.tel_numero
                                            from telefono te
                                            where te.tte_id in (1,3) and te.tel_id = e.ent_telefono_principal
                                            union
                                            select te.tel_numero
                                            from telefono_entidad ten join telefono te on ten.tel_id = te.tel_id
                                            where te.tte_id in (1,3) and ten.ent_id = e.ent_id) as tabla) as telefono,
                                    (select string_agg(tel_numero, '/')
                                    from (select te.tel_numero
                                            from telefono te
                                            where te.tte_id = 2 and te.tel_id = e.ent_telefono_principal
                                            union
                                            select te.tel_numero
                                            from telefono_entidad ten join telefono te on ten.tel_id = te.tel_id
                                            where te.tte_id = 2 and ten.ent_id = e.ent_id) as tabla) as celular,
                                    e.ent_email as email,
                                    pr.pro_id,
                                    concat(trim(tp.tpr_nombre), ' / ', pr.pro_nombre) as producto,
                                    c.cfa_id,
                                    concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as factura,
                                    c.cfa_fecha as fecha_compra,
                                    m.mar_nombre as marca,
                                    c2.numero as num_despacho,
                                    string_agg(concat(d2.serie, ' ', (case d2.tipo when 'C' then 'COMPRESOR' when 'E' then 'EVAPORADOR' end)), '/')  as serie,
                                    12 as garantia_marca,
                                    concat(ve.dir_calle_principal, ' ', ve.dir_numeracion, ' ', ve.dir_calle_secundaria, ' / ', trim(vu.ubi_nombre), ' - ', trim(vu.ubi2_nombre)) as ubicacion,
                                    c.cfa_id as cfa_id_gex,
                                    concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as factura_gex,
                                    cg.num_meses as meses_gex,
                                    c.cfa_fecha as fecha_desde,
                                    c.cfa_fecha + cg.num_meses * interval'1 month'  as fecha_hasta,
                                    (select pc.km_garantia from gex.producto_config pc where pc.pro_id = pr.pro_id) as km_garantia,
                                    (2) as km_factor,
                                    (select pc.tipo_servicio from gex.producto_config pc where pc.pro_id = pr.pro_id) as tipo_servicio
                            from cfactura c join dfactura d on c.cfa_id = d.cfa_id
                                            join producto pr on d.pro_id = pr.pro_id
                                            join marca m on pr.mar_id = m.mar_id
                                            join tipo_producto tp on pr.tpr_id = tp.tpr_id
                                            join puntoventa p on c.pve_id = p.pve_id
                                            join almacen a on p.alm_id = a.alm_id
                                            join ctipocom t on c.cti_id = t.cti_id
                                            join cliente c1 on c.cli_id = c1.cli_id
                                            join entidad e on c1.ent_id = e.ent_id
                                            join v_entidad ve on e.ent_id = ve.ent_id
                                            join v_ubicacion vu on c1.ubi_id = vu.ubi3_id
                                            join gex.cdespacho c2 on c2.cfa_id = c.cfa_id
                                            join gex.ddespacho d2 on c2.numero = d2.numero and d2.pro_id = pr.pro_id
                                            join cgex cg on cg.id_dfactura = d.dfac_id and cg.pro_id_gex = d.id_producto_gex
                            where c.cfa_id = " . $factura . " and not exists (select 1 from gex.contrato_gex cg where cg.cfa_id = d.cfa_id and cg.pro_id = d.pro_id)
                            group by p.alm_id, a.alm_nombre, e.ent_nombres, e.ent_apellidos, ve.ent_tipo_identificacion, ve.ent_identificacion, ve.dir_calle_principal, ve.dir_numeracion,
                                        ve.dir_calle_secundaria, vu.ubi_nombre, vu.ubi2_nombre, e.ent_email, pr.pro_id, tp.tpr_nombre, pr.pro_nombre, c.cfa_id, t.cti_sigla, a.alm_codigo, p.pve_numero,
                                        c.cfa_numero, c.cfa_fecha, m.mar_nombre, c2.numero, cg.num_meses, c.cfa_fecha, a.ubi_id, e.ent_telefono_principal, e.ent_id");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byContrato($almacen, $numero)
    {
        $data = ContratoGex::get()->where('alm_id', $almacen)->where('numero', $numero)->first();
        $data['almacenes'] = DB::selectOne("select alm_id, alm_nombre from almacen a where a.alm_id = " . $data['alm_id']);
        $data['facturas'] = DB::selectOne("select c.cfa_id, concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as numero
                                            from cfactura c join puntoventa p on c.pve_id = p.pve_id
                                                            join ctipocom t on c.cti_id = t.cti_id
                                                            join dfactura d on c.cfa_id = d.cfa_id
                                            where p.alm_id = " . $almacen . " and c.cfa_id = " . $data['cfa_id'] . "
                                            group by c.cfa_id, t.cti_sigla,p.alm_id, p.pve_numero, c.cfa_numero");

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Contrato Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Contrato no existe', []));
        }
    }

    public function grabaContrato(Request $request)
    {
        try {
            $numero = 0;
            $almacen = 0;
            $numeros = [];
            $contratos = $request->all();

            DB::transaction(function() use ($contratos, $numero, &$numeros, &$almacen){
                date_default_timezone_set("America/Guayaquil");
                
                foreach ($contratos as $c) {
                    if ($numero == 0) {
                        $folio = FolioContratos::get()->where('alm_id',$c['alm_id'])->first();
                        $numero = $folio['folio']  + 1;
                    } else {
                        $numero += 1;
                    }

                    array_push($numeros, $numero);
                    $almacen = $c['alm_id'];
                    
                    $alm_id = $c['alm_id'];
                    $fecha = date("Y-m-d h:i:s");
                    $nom_almacen = $c['nom_almacen'];
                    $nom_cliente = $c['nom_cliente'];
                    $tipo_identificacion = $c['tipo_identificacion'];
                    $identificacion = $c['identificacion'];
                    $provincia = $c['provincia'];
                    $ciudad = $c['ciudad'];
                    $direccion = $c['direccion'];
                    $telefono = $c['telefono'];
                    $celular = $c['celular'];
                    $email = $c['email'];
                    $pro_id = $c['pro_id'];
                    $producto = $c['producto'];
                    $cfa_id = $c['cfa_id'];
                    $factura = $c['factura'];
                    $fecha_compra = $c['fecha_compra'];
                    $marca = $c['marca'];
                    $num_despacho = $c['num_despacho'];
                    $serie = $c['serie'];
                    $garantia_marca = $c['garantia_marca'];
                    $ubicacion = $c['ubicacion'];
                    $cfa_id_gex = $c['cfa_id_gex'];
                    $factura_gex = $c['factura_gex'];
                    $meses_gex = $c['meses_gex'];
                    $fecha_desde = $c['fecha_desde'];
                    $fecha_hasta = $c['fecha_hasta'];
                    $usuario_crea = $c['usuario_crea'];
                    $usuario_modifica = $c['usuario_modifica'];
                    $fecha_crea = date("Y-m-d h:i:s");
                    $km_garantia = $c['km_garantia'];
                    $km_factor = $c['km_factor'];
                    $tipo_servicio = $c['tipo_servicio'];

                    DB::table('gex.contrato_gex')->insert(
                        [
                        'alm_id' => $alm_id,
                        'numero' => $numero,
                        'fecha' => $fecha,
                        'nom_almacen' => $nom_almacen,
                        'nom_cliente' => $nom_cliente,
                        'tipo_identificacion' => $tipo_identificacion,
                        'identificacion' => $identificacion,
                        'provincia' => $provincia,
                        'ciudad' => $ciudad,
                        'direccion' => $direccion,
                        'telefono' => $telefono,
                        'celular' => $celular,
                        'email' => $email,
                        'pro_id' => $pro_id,
                        'producto' => $producto,
                        'cfa_id' => $cfa_id,
                        'factura' => $factura,
                        'fecha_compra' => $fecha_compra,
                        'marca' => $marca,
                        'num_despacho' => $num_despacho,
                        'serie' => $serie,
                        'garantia_marca' => $garantia_marca,
                        'ubicacion' => $ubicacion,
                        'cfa_id_gex' => $cfa_id_gex,
                        'factura_gex' => $factura_gex,
                        'meses_gex' => $meses_gex,
                        'fecha_desde' => $fecha_desde,
                        'fecha_hasta' => $fecha_hasta,
                        'usuario_crea' => $usuario_crea,
                        'usuario_modifica' => $usuario_modifica,
                        'fecha_crea' => $fecha_crea,
                        'km_garantia' => $km_garantia,
                        'km_factor' => $km_factor,
                        'tipo_servicio' => $tipo_servicio,
                        ]);

                    DB::table('gex.folios_contratos')->updateOrInsert(
                        ['alm_id' => $alm_id],
                        [
                        'alm_id' => $alm_id,
                        'folio' => $numero,
                        ]);
                }
            });

            $data = [];

            foreach ($numeros as $n){
                array_push($data, ContratoGex::get()->where('alm_id', $almacen)->where('numero', $n)->first());
            }
            
            return response()->json(RespuestaApi::returnResultado('success', 'Contratos generados con exito', $data));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaContrato($almacen, $numero) {
        try {
            ContratoGex::where('alm_id',$almacen)->where('numero',$numero)->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Contrato eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}