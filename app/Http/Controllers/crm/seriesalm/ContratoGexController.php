<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ContratoGex;
use App\Models\crm\series\Despacho;
use App\Models\crm\series\Inventario;
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
            $facturas = DB::select("SELECT
	            e.ent_identificacion,
	            e.ent_nombres || ' '||e.ent_apellidos as cliente,
	            e.ent_email,
	            dir.dir_calle_principal,
	            dir.dir_calle_secundaria,
	            prv.prv_nombre as provincia_cli, cnt.ctn_nombre as canton_cli,
                c.cfa_id,
                c.alm_id,
                c.alm_nombre,
                c.alm_nombre_tmp,
                c.alm_nombre_tmp2,
                (select u2.ubi_nombre from public.almacen a
	            inner join public.ubicacion u on u.ubi_id = a.ubi_id
	            inner join public.ubicacion u2 on u2.ubi_id = u.ubi_reporta
	            where a.alm_id = c.alm_id) as provincia,
                c.cfa_fecha,
                c.cfa_periodo,
                c.factura,
                d.dfac_id,
                d.pro_id,
                p.pro_codigo,
                p.pro_nombre,
                m.mar_nombre,
                12 as garantia_marca,
                d.prod_gex,
                d.id_producto_gex,
                cg.num_meses,
                cg.valor_gex,
                cast(c.cfa_fecha + 366 * interval'1 day' as date) as fecha_desde,
                cast((c.cfa_fecha + 366 * interval'1 day') + cg.num_meses * interval'1 month' as date) as fecha_hasta
            FROM crm.av_cfactura_cnotacre_lineal c
            INNER JOIN public.dfactura d ON d.cfa_id = c.cfa_id AND c.ccm_estado = 2 AND c.cfa_periodo >= 2022 AND d.prod_gex IS NOT null AND d.id_producto_gex IS NOT null and c.alm_id = ?
            LEFT JOIN crm.contrato_gex ctg ON ctg.cfa_id = c.cfa_id
            inner join public.cfactura c2 on c2.cfa_id = c.cfa_id  AND ctg.cfa_id IS NULL
            INNER JOIN public.producto p ON p.pro_id = d.pro_id
            inner join public.marca m on m.mar_id = p.mar_id
            inner join public.cliente cli on cli.cli_id= c2.cli_id
            inner join public.entidad e on e.ent_id = cli.ent_id
            inner join public.cgex cg on cg.id_dfactura = d.dfac_id
            left join public.direccion dir on dir.dir_id = e.ent_direccion_principal
            left join public.cliente_anexo clia on clia.cli_id = cli.cli_id
            left join public.canton cnt on cnt.ctn_id = clia.ctn_id
            left join public.provincia prv on prv.prv_id = cnt.prv_id
            WHERE nota_credito IS null and c.cfa_id = 446464
            ORDER BY 2 asc;", [$almId]);

            $data = (object)[
                "facturas" => $facturas
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }
}
