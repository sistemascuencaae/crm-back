<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\GaleriaService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) que usa la tienda para mostrar una galería por su clave
 * (pagos_depositos, pagos_tarjetas, marcas_aliadas, beneficios_fotos…).
 * Una galería sin imágenes visibles responde una lista vacía: la tienda simplemente no la muestra.
 */
class GaleriaPublicaController extends Controller
{
    public function listar(GaleriaService $servicio, string $clave)
    {
        try {
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Galería', $servicio->listarPublicas($clave)))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la galería pública', ['clave' => $clave, 'error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la galería', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}