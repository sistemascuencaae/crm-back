<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\EspanaMotosService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) que usa la tienda para mostrar la pantalla España Motos.
 * Si no está publicada responde status 'error' y data null: la tienda muestra "Página no encontrada".
 */
class EspanaMotosPublicoController extends Controller
{
    // GET public/pagina-web/espana-motos
    public function buscar(EspanaMotosService $servicio)
    {
        try {
            $pantalla = $servicio->publica();

            if ($pantalla === null) {
                return response()
                    ->json(RespuestaApi::returnResultado('error', 'Página no encontrada', null))
                    ->header('Cache-Control', 'no-cache');
            }

            return response()
                ->json(RespuestaApi::returnResultado('success', 'España Motos', $pantalla))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar España Motos', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}
