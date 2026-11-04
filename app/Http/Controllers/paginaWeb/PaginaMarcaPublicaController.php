<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\PaginaMarcaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints públicos (sin autenticación) que usa la tienda para mostrar una página de marca por su dirección y su
 * cuadrícula de productos por páginas.
 * Si no existe o está oculta responde status 'error' y data null: la tienda muestra "Página no encontrada".
 */
class PaginaMarcaPublicaController extends Controller
{
    // GET public/pagina-web/marcas/{direccion}
    public function buscar(PaginaMarcaService $servicio, string $direccion)
    {
        try {
            $pagina = $servicio->publica($direccion);

            if ($pagina === null) {
                return response()
                    ->json(RespuestaApi::returnResultado('error', 'Página no encontrada', null))
                    ->header('Cache-Control', 'no-cache');
            }

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Página de marca', $pagina))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la página de marca', ['direccion' => $direccion, 'error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/pagina-web/marcas/{direccion}/productos?page=1&per_page=15&destacados=1 (destacados: solo los destacados)
    //   → la misma forma que la búsqueda del shop, sin opciones: {productos: [tarjetas], total, pagina, por_pagina, ultima_pagina}
    public function productos(Request $request, PaginaMarcaService $servicio, string $direccion)
    {
        try {
            $productos = $servicio->productosPublicos(
                $direccion,
                $request->boolean('destacados'),
                $request->query('page'),
                $request->query('per_page')
            );

            if ($productos === null) {
                return response()
                    ->json(RespuestaApi::returnResultado('error', 'Página no encontrada', null))
                    ->header('Cache-Control', 'no-cache');
            }

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Productos', $productos))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron cargar los productos de la página de marca', ['direccion' => $direccion, 'error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los productos', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}