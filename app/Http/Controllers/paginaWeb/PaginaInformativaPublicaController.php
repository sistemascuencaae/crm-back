<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\PaginaInformativaService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) que usa la tienda para mostrar una página informativa.
 * Solo devuelve páginas visibles, sin datos internos.
 */
class PaginaInformativaPublicaController extends Controller
{
    public function buscar(PaginaInformativaService $servicio, string $clave)
    {
        try {
            $pagina = $servicio->buscarPublica($clave);

            // HTTP 404 a propósito: la tienda muestra "Página no disponible"
            if ($pagina === null) {
                return response()
                    ->json(RespuestaApi::returnResultado('error', 'Página no disponible', null), 404)
                    ->header('Cache-Control', 'no-store');
            }

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            // La caché de 10 min del servidor se limpia sola al guardar desde el CRM.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Página', $pagina))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la página pública', ['clave' => $clave, 'error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }

    // Diálogo de documentos: todos los documentos visibles, por carpeta y en su orden.
    // Una lista vacía también es éxito (el diálogo muestra que no hay documentos).
    public function documentos(PaginaInformativaService $servicio)
    {
        try {
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Documentos', $servicio->listarPublicas()))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron cargar los documentos públicos', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los documentos', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}