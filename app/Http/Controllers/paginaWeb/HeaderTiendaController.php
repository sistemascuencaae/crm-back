<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Header de la tienda (público, sin login): el header que se muestra ahora (ecommerce.fn_header_tienda_publico) y las
// imágenes de los headers (el logo). El mantenimiento está en ecommerce/HeaderController (CRM), que borra la caché al guardar.
class HeaderTiendaController extends Controller
{
    // Imágenes de los headers (el logo): carpeta privada storage/app/headers; se piden por
    // api/public/headers/imagenes/{nombre}. Nombre aleatorio de 40 letras y números + .webp (nunca cambia de contenido).
    private const CARPETA_IMAGENES = 'headers';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';

    // Lo que lee la tienda (tienda()): se guarda 60 s; cada cambio guardado en el CRM lo borra (limpiarCache). Los 60 s
    // cubren el cambio de header por fechas (empieza o termina una campaña) sin que nadie guarde nada.
    private const CACHE_TIENDA = 'ecommerce.header_tienda';
    private const SEGUNDOS_CACHE_TIENDA = 60;

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // Pública: el header que la tienda muestra ahora, listo para dibujar (ecommerce.fn_header_tienda_publico decide qué
    // sale: partes visibles, textos activos, links activos y vigentes con sus sublinks, solo los campos de la tienda).
    // data = null si todavía no hay headers (la tienda usa su diseño de respaldo).
    public function tienda()
    {
        try {
            $header = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $fila = DB::selectOne('SELECT ecommerce.fn_header_tienda_publico() AS header');

                return $fila->header ? json_decode($fila->header, true) : null;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Header de la tienda', $header));
        } catch (\Throwable $th) {
            Log::error('No se pudo leer el header de la tienda', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer el header.', null));
        }
    }

    // Pública: entrega una imagen de la carpeta de headers y nada más (el nombre se revisa antes de abrir el archivo).
    public function verImagen(string $nombre)
    {
        $ruta = self::CARPETA_IMAGENES . '/' . $nombre;

        if (!preg_match(self::NOMBRE_IMAGEN, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
