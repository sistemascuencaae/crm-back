<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\PaginaTiendaService;
use Illuminate\Support\Facades\Log;

// Públicas: las páginas del CRM (Ecommerce > Páginas) para la tienda. Sin sesión.
//   GET public/paginas/inicio   → la página marcada como Inicio (data = null si no hay: la tienda usa su inicio fijo)
//   GET public/paginas/{ruta}   → una página activa por su dirección (404 si no existe o está inactiva)
// Sin caché de la respuesta: lo que se guarda en el CRM sale al momento (los precios y el stock sí vienen de la caché de
// publicados de la tienda).
class PaginaTiendaController extends Controller
{
    public function __construct(private PaginaTiendaService $paginas)
    {
    }

    public function inicio()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Página de inicio', $this->paginas->publica(null)))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página de inicio', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null), 500);
        }
    }

    public function ver(string $ruta)
    {
        try {
            $pagina = $this->paginas->publica($ruta);
            if (!$pagina) {
                return response()->json(RespuestaApi::returnResultado('error', 'La página no existe.', null), 404);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Página', $pagina))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página', ['ruta' => $ruta, 'error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null), 500);
        }
    }
}
