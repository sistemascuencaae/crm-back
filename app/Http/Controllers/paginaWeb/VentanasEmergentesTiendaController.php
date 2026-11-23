<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Publicidad del inicio de la tienda (público, sin login): la que toca hoy (ecommerce.fn_ventana_emergente_hoy)
// y sus imágenes. La base decide cuál (fechas, predeterminada, hora de Ecuador); la tienda solo la muestra. El
// mantenimiento está en ecommerce/VentanasEmergentesController (CRM), que borra la caché al guardar.
class VentanasEmergentesTiendaController extends Controller
{
    // Se guarda 10 minutos por día (la clave lleva la fecha de Ecuador: al cambiar el día se pregunta de nuevo).
    private const CACHE_TIENDA = 'ecommerce.ventanas_emergentes.hoy.';
    private const SEGUNDOS_CACHE_TIENDA = 600;

    // Dónde se guardan las imágenes (carpeta ecommerce/ventanas, NAS o local) y qué tipos hay.
    public const CARPETA = ArchivosPaginaWeb::CARPETA . '/ventanas';
    public const TIPOS = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
    private const NOMBRE = '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/';

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::claveCache());
    }

    // Dirección pública de una imagen (null si no hay).
    public static function urlImagen(?string $nombre): ?string
    {
        return $nombre ? url('api/public/ventanas-emergentes/imagenes/' . $nombre) : null;
    }

    // GET public/ventanas-emergentes/hoy -> {id, nombre, imagen} o null si hoy no sale ninguna.
    public function hoy()
    {
        try {
            $ventana = Cache::remember(self::claveCache(), self::SEGUNDOS_CACHE_TIENDA, function () {
                $v = DB::selectOne('SELECT * FROM ecommerce.fn_ventana_emergente_hoy()');

                // Cache::remember no guarda null: "sin ventana" se guarda como false.
                return $v ? [
                    'id' => (int) $v->ven_id,
                    'nombre' => $v->ven_nombre,
                    'imagen' => self::urlImagen($v->ven_imagen),
                ] : false;
            });

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Publicidad de inicio de hoy', $ventana ?: null))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la publicidad de inicio de la tienda', ['error' => $th->getMessage()]);

            // HTTP 200 como todo el estándar: la tienda ve status 'error' y simplemente no muestra la publicidad.
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la publicidad de inicio', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/ventanas-emergentes/imagenes/{nombre}: la imagen, del NAS o de local (ArchivosPaginaWeb::entregar).
    // Cada imagen nueva tiene otro nombre: el navegador la guarda un año.
    public function verImagen(string $nombre)
    {
        if (!preg_match(self::NOMBRE, $nombre, $m)) {
            abort(404);
        }

        return ArchivosPaginaWeb::entregar(self::CARPETA . '/' . $nombre, self::TIPOS[$m[1]]);
    }

    private static function claveCache(): string
    {
        return self::CACHE_TIENDA . now('America/Guayaquil')->toDateString();
    }
}
