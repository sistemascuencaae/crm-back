<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\FranjaDetalleService;
use App\Servicios\PaginaWeb\ProductoTiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints públicos (sin autenticación) de los productos de la tienda: el shop (una página de productos publicados
 * con las opciones del panel de filtros, ver ProductoTiendaService::buscarTienda) y el detalle de un producto.
 * Las respuestas no se guardan en caché (las franjas sí, en su servicio).
 */
class ProductoTiendaPublicoController extends Controller
{
    // GET public/pagina-web/productos/{proId}   → {producto, franjas, relacionados}
    // Si no está publicado o su categoría no se ve: code 404 en el cuerpo (HTTP 200), status 'error' y data null; la
    // tienda muestra "Producto no disponible". Una falla interna responde code 500: así la tienda los distingue.
    public function detalle(int $proId, ProductoTiendaService $productos, FranjaDetalleService $franjas)
    {
        try {
            $detalle = $productos->detalle($proId);

            if ($detalle === null) {
                // RespuestaApi no deja elegir el código del error (siempre 500): se reemplaza solo el code
                return response()
                    ->json(['code' => 404] + RespuestaApi::returnResultado('error', 'Producto no disponible', null))
                    ->header('Cache-Control', 'no-cache');
            }

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Producto', [
                    'producto'     => $detalle['producto'],
                    'franjas'      => $franjas->publicas(),
                    'relacionados' => $detalle['relacionados'],
                ]))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar el producto', ['pro_id' => $proId, 'error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el producto', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/pagina-web/productos?search=&categoria=&marca=&price_min=&price_max=&in_stock=&motos=&sort=&page=&per_page=
    public function buscar(Request $request, ProductoTiendaService $servicio)
    {
        try {
            $resultado = $servicio->buscarTienda($request->only(ProductoTiendaService::FILTROS_SHOP));

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Productos', $resultado))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron buscar los productos de la tienda', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los productos', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}
