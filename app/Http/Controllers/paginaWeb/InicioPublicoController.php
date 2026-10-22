<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\InicioSeccionService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) con todo el inicio de la tienda: {secciones: [...], ahora},
 * solo las secciones visibles y en su orden (ver InicioSeccionService::publica).
 * "ahora" es la hora de la base, calculada en cada petición (fuera de la caché).
 */
class InicioPublicoController extends Controller
{
    // GET public/pagina-web/inicio
    public function buscar(InicioSeccionService $servicio)
    {
        try {
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Inicio', $servicio->publica() + ['ahora' => $servicio->ahora()]))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar el inicio', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el inicio', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}