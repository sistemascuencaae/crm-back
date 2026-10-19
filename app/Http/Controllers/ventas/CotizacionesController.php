<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizaciones guardadas (ae/ventas/COTIZACIONES): listado paginado y una cotizacion completa.
// El negocio vive en ventas.fn_ccotizacion_* (PLANES/COTIZADOR/produccion_cotizaciones.sql): este
// controller solo valida la forma y traduce la respuesta. Solo lectura.
class CotizacionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Filtros opcionales: fecha_desde y fecha_hasta (Y-m-d; por defecto los ultimos 7 dias),
    // tipo, busqueda, pagina y tamanio (1-100).
    public function listCotizaciones(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha_desde' => 'nullable|date_format:Y-m-d',
                'fecha_hasta' => 'nullable|date_format:Y-m-d',
                'tipo'        => 'nullable|in:CONTADO,CREDITO',
                'busqueda'    => 'nullable|string|max:100',
                'pagina'      => 'nullable|integer|min:1',
                'tamanio'     => 'nullable|integer|min:1|max:100',
            ], [
                'fecha_desde.date_format' => 'La fecha desde no es válida.',
                'fecha_hasta.date_format' => 'La fecha hasta no es válida.',
                'tipo.in'                 => 'El tipo debe ser CONTADO o CREDITO.',
                'busqueda.string'         => 'La búsqueda no es válida.',
                'busqueda.max'            => 'La búsqueda admite hasta 100 caracteres.',
                'pagina.integer'          => 'La página no es válida.',
                'pagina.min'              => 'La página no es válida.',
                'tamanio.integer'         => 'El tamaño de página no es válido.',
                'tamanio.min'             => 'El tamaño de página no es válido.',
                'tamanio.max'             => 'El tamaño de página no es válido.',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $filtros = $request->only(['fecha_desde', 'fecha_hasta', 'tipo', 'busqueda', 'pagina', 'tamanio']);
            $fila = DB::selectOne('SELECT ventas.fn_ccotizacion_listar_paginacion(?::jsonb) AS resultado', [json_encode((object) $filtros)]);
            $respuesta = json_decode($fila->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $respuesta['data']));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las cotizaciones', $e->getMessage()));
        }
    }

    // La cotizacion completa: cabecera, productos y aportes de cada producto.
    public function getCotizacion($id)
    {
        try {
            if (!ctype_digit((string) $id) || (int) $id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cotización no válida', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_ccotizacion_buscar(?) AS resultado', [(int) $id]);
            $respuesta = json_decode($fila->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $respuesta['data']));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la cotización', $e->getMessage()));
        }
    }
}
