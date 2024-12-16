<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ContratoGex;
use App\Models\crm\series\Despacho;
use App\Models\crm\series\Inventario;
use App\Models\crm\seriesalm\ContratoGexCRM;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratoGexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function loadInitialData($almId)
    {
        try {
            $facturas = DB::select("SELECT p.alm_id,
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
                        cast(c.cfa_fecha as date) as fecha_compra,
                        m.mar_nombre as marca,
                        12 as garantia_marca,
                        concat(ve.dir_calle_principal, ' ', ve.dir_numeracion, ' ', ve.dir_calle_secundaria, ' / ', trim(vu.ubi_nombre), ' - ', trim(vu.ubi2_nombre)) as ubicacion,
                        c.cfa_id as cfa_id_gex,
                        concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', p.pve_numero, ' - ',  c.cfa_numero) as factura_gex,
                        cg.num_meses as meses_gex,
                        cast(c.cfa_fecha + 366 * interval'1 day' as date) as fecha_desde,
                        cast((c.cfa_fecha + 366 * interval'1 day') + cg.num_meses * interval'1 month' as date) as fecha_hasta,
                        (select pc.km_garantia from gex.producto_config pc where pc.pro_id = pr.pro_id) as km_garantia,
                        (2) as km_factor,
                        (select pc.tipo_servicio from gex.producto_config pc where pc.pro_id = pr.pro_id) as tipo_servicio,
                        d.id_producto_gex
                from cfactura c
				                join crm.av_cfactura_cnotacre_lineal c4 on c4.cfa_id = c.cfa_id
				                join dfactura d on c.cfa_id = d.cfa_id AND c4.ccm_estado = 2 AND c.cfa_periodo >= 2022 AND d.prod_gex IS NOT null AND d.id_producto_gex IS NOT null and c4.alm_id = 1
                                join puntoventa p on c.pve_id = p.pve_id
                                join almacen a on p.alm_id = a.alm_id
                                join producto pr on d.pro_id = pr.pro_id
                                join tipo_producto tp on pr.tpr_id = tp.tpr_id
                                join marca m on pr.mar_id = m.mar_id
                                join ctipocom t on c.cti_id = t.cti_id
                                join cliente c1 on c.cli_id = c1.cli_id
                                join entidad e on c1.ent_id = e.ent_id
                                join v_entidad ve on e.ent_id = ve.ent_id
                                join v_ubicacion vu on c1.ubi_id = vu.ubi3_id
                                join cgex cg on cg.id_dfactura = d.dfac_id and cg.pro_id_gex = d.id_producto_gex
                where c.cfa_id = 446464 and not exists (select 1 from crm.contrato_gex cg where cg.cfa_id = d.cfa_id and cg.pro_id = d.pro_id)
                group by p.alm_id, a.alm_nombre, e.ent_nombres, e.ent_apellidos, ve.ent_tipo_identificacion, ve.ent_identificacion, ve.dir_calle_principal, ve.dir_numeracion,
                        ve.dir_calle_secundaria, vu.ubi_nombre, vu.ubi2_nombre, e.ent_email, pr.pro_id, tp.tpr_nombre, pr.pro_nombre, c.cfa_id, t.cti_sigla, a.alm_codigo, p.pve_numero,
                        c.cfa_numero, c.cfa_fecha, m.mar_nombre,cg.num_meses, c.cfa_fecha, a.ubi_id, e.ent_telefono_principal, e.ent_id, d.id_producto_gex;");
            $data = (object)[
                "facturas" => $facturas
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function generarContratoCRM(Request $request)
    {
        try {

            $dataContrato = $request->all();

            $almId = $request->input('alm_id');
            $ultimoFolio = DB::selectOne("SELECT * from gex.folios_contratos fc  where alm_id = 1 order by folio desc limit 1;");


            $dataContrato["numero"] = $ultimoFolio->folio;
            $dataContrato["fecha"] = Carbon::now();
            $data = ContratoGexCRM::create($dataContrato);


            DB::table('gex.folios_contratos')->updateOrInsert(
                ['alm_id' => $almId],
                [
                    'alm_id' => $almId,
                    'folio' => $ultimoFolio->folio,
                ]
            );


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }
}
