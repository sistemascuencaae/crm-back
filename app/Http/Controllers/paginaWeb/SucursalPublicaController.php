<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Servicios\PaginaWeb\SucursalService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público (sin autenticación) que usa la tienda.
 * Devuelve solo ciudades y sucursales activas, sin datos internos.
 */
class SucursalPublicaController extends Controller
{
    public function listar(SucursalService $servicio): JsonResponse
    {
        $resultado = $servicio->listarPublicas();

        // Igual que SeccionPublicaController: si la función falla, 500 y sin caché
        if (empty($resultado['ok'])) {
            return response()
                ->json($resultado, 500)
                ->header('Cache-Control', 'no-store');
        }

        // no-cache: el navegador siempre pregunta al servidor, así los cambios del CRM se ven al instante.
        // La caché de 10 min del servidor se mantiene y se limpia sola al guardar desde el CRM.
        return response()
            ->json($resultado)
            ->header('Cache-Control', 'no-cache');
    }
}