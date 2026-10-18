<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\EncabezadoPiePublicoService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) con el header y el footer que muestra la tienda.
 * GET public/pagina-web/secciones (la misma ruta de antes, para que la tienda no cambie de URL).
 */
class EncabezadoPiePublicoController extends Controller
{
    public function listar(EncabezadoPiePublicoService $servicio)
    {
        try {
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Encabezado y pie de página', $servicio->listar()))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar el encabezado y pie de página', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el encabezado y pie de página', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}