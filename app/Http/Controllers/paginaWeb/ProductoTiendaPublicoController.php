<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ProductoTiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) del shop de la tienda: una página de productos publicados con las opciones
 * del panel de filtros (ver ProductoTiendaService::buscarTienda). La respuesta no se guarda en caché.
 */
class ProductoTiendaPublicoController extends Controller
{
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
