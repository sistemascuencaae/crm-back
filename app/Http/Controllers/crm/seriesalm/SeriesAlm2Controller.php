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
                                    cdesp.numero as numero_cdespacho, cdesp.fecha as fecha_cdespacho, 
                                    ddesp.serie, ddesp.tipo as tipo_producto,
                                    prod.pro_codigo || ' - ' || prod.pro_nombre as codigo_mas_producto,
                                    nce_prod.factura, nce_prod.comprobante, nce_prod.fecha_nota_credito
                                        from gex.cdespacho cdesp 
                                            join gex.ddespacho ddesp on ddesp.numero = cdesp.numero 
                                            join public.cfactura cfae on cfae.cfa_id = cdesp.cfa_id 
                                            join public.producto prod on prod.pro_id = ddesp.pro_id 
                                            join public.av_notascredito_producto_periodo_actual_menos1 nce_prod on nce_prod.cfa_id = cdesp.cfa_id;");

            $respuestaConsulta = collect($data);

            $grupoPorNotaCredito = $respuestaConsulta->groupBy('comprobante')->map(function ($row) {
                return [
                    'numero_cdespacho' => $row[0]->numero_cdespacho,
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
