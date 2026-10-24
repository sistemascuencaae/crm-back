<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\SucursalService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sin autenticación) que usa la tienda.
 * Devuelve solo ciudades y sucursales activas, sin datos internos.
 */
class SucursalPublicaController extends Controller
{
    public function listar(SucursalService $servicio)
    {
        try {
            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            // La caché de 10 min del servidor se limpia sola al guardar desde el CRM.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Sucursales', $servicio->listarPublicas()))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron listar las sucursales públicas', ['error' => $th->getMessage()]);

            // HTTP 500 a propósito: la tienda trata la falla como error y el navegador no la guarda
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las tiendas', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}