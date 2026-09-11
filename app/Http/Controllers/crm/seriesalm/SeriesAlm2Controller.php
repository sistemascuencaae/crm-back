<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;

class SeriesAlm2Controller extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // public function listNotasCreditoDespachos()
    // {
    //     try {
    //         $data = DB::select("SELECT
    //                                 cdesp.numero as numero_cdespacho, cdesp.fecha as fecha_cdespacho,
    //                                 ddesp.serie, ddesp.tipo as tipo_producto,
    //                                 prod.pro_codigo || ' - ' || prod.pro_nombre as codigo_mas_producto,
    //                                 nce_prod.factura, nce_prod.comprobante, nce_prod.fecha_nota_credito
    //                                     from gex.cdespacho cdesp
    //                                         join gex.ddespacho ddesp on ddesp.numero = cdesp.numero
    //                                         join public.cfactura cfae on cfae.cfa_id = cdesp.cfa_id
    //                                         join public.producto prod on prod.pro_id = ddesp.pro_id
    //                                         join public.av_notascredito_producto_periodo_actual_menos1 nce_prod on nce_prod.cfa_id = cdesp.cfa_id;");

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
    //     }
    // }

    public function listNotasCreditoDespachos()
    {
        try {
            $data = DB::select("SELECT
                    cd.numero as numero_cdespacho,
                    cd.bod_id,
                    cd.fecha as fecha_cdespacho,
                    dd.serie,
                    dd.tipo as tipo_producto,
                    pro.pro_codigo || ' - ' || pro.pro_nombre as codigo_mas_producto,
                    v.factura,
                    v.comprobante,
                    v.fecha_nota_credito
                    from gex.cdespacho cd
                    inner join gex.ddespacho dd on dd.numero = cd.numero
                    inner join public.producto pro on pro.pro_id = dd.pro_id
                    inner join public.cfactura cfa on cfa.cfa_id = cd.cfa_id
                    inner join public.ctipocom cti on cti.cti_id = cfa.cti_id
                    inner join public.puntoventa pve on pve.pve_id = cfa.pve_id
                    inner join public.almacen alm on alm.alm_id = pve.alm_id
                    inner join public.av_notascredito_producto_periodo_actual_menos1 v on v.factura = concat(cti.cti_sigla,'-',alm.alm_codigo,'-',pve.pve_numero,'-',cfa.cfa_numero) and tipo_nota = 'NCE'
                    left join gex.res_serie_eliminada re on re.cfa_id = cd.cfa_id
                    where re.cfa_id isnull
                    group by 1,2,3,4,5,6,7,8,9;");

            $respuestaConsulta = collect($data);

            $grupoPorNotaCredito = $respuestaConsulta->groupBy('comprobante')->map(function ($row) {
                return [
                    'numero_cdespacho' => $row[0]->numero_cdespacho,
                    'bodega' => $row[0]->bod_id,
                    'fecha_cdespacho' => $row[0]->fecha_cdespacho,
                    'factura' => $row[0]->factura,
                    'comprobante' => $row[0]->comprobante,
                    'fecha_nota_credito' => $row[0]->fecha_nota_credito,

                    'productos' => $row->map(function ($item) {
                        return [
                            'serie' => $item->serie,
                            'tipo_producto' => $item->tipo_producto,
                            'codigo_mas_producto' => $item->codigo_mas_producto,
                        ];
                    })->toArray()

                ];
            })->values();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $grupoPorNotaCredito));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
