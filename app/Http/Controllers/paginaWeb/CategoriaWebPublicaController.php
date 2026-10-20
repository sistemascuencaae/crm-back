<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\CategoriaWebService;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints públicos (sin autenticación) de categorías para la tienda.
 * Una lista vacía es válida: la tienda simplemente no muestra esa parte.
 */
class CategoriaWebPublicaController extends Controller
{
    // GET public/pagina-web/categorias-destacadas → [{tpr_id, nombre, imagen}]
    public function listar(CategoriaWebService $servicio)
    {
        try {
            return $this->ok('Categorías destacadas', $servicio->listarPublicas());
        } catch (\Throwable $th) {
            return $this->error('No se pudieron cargar las categorías destacadas', $th);
        }
    }

    // GET public/pagina-web/categorias-menu → [{id, nombre, hijos: [...]}]
    public function menu(CategoriaWebService $servicio)
    {
        try {
            return $this->ok('Menú de categorías', $servicio->listarMenu());
        } catch (\Throwable $th) {
            return $this->error('No se pudo cargar el menú de categorías', $th);
        }
    }

    // -----------------------------------------------------------------

    private function ok(string $mensaje, array $datos)
    {
        return response()
            ->json(RespuestaApi::returnResultado('success', $mensaje, $datos))
            ->header('Cache-Control', 'no-cache');
    }

    private function error(string $mensaje, \Throwable $th)
    {
        Log::error($mensaje, ['error' => $th->getMessage()]);

        return response()
            ->json(RespuestaApi::returnResultado('error', $mensaje, null), 500)
            ->header('Cache-Control', 'no-store');
    }
}