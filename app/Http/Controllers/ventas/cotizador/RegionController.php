<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;

// Version 1.0
// Ventas > Margen y aportes: el ojito de Región (lo abren los formularios de Margen y de Aporte). Los almacenes con sus puntos
// de venta activos de una región (public.region, puntoventa y almacen: la región es del punto de venta, así que un almacén
// puede tener puntos en varias regiones). Solo lectura y sin modelo (catálogos del ERP). La consulta vive en
// ventas.fn_region_puntos_venta_listar (PLANES/COTIZADOR/produccion_cmargen.sql). Viene de
// ventas/MargenCotizadorController@listPuntosVentaByRegionId (Fase 2 del plan del cotizador, 11-nov-2026).
class RegionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listPuntosVentaRegion/{reg_id} — la región, cuántos almacenes y puntos de venta tiene, y cada almacén con
    // sus puntos de venta. Antes listPuntosVentaByRegionId.
    public function listPuntosVentaRegion($reg_id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_region_puntos_venta_listar(?) AS resultado', [(int) $reg_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los puntos de venta de la región', $e->getMessage()));
        }
    }
}
