<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Video de inicio de la tienda (público, sin login): el que toca hoy (ecommerce.fn_video_inicio_hoy) y sus archivos.
// La base decide cuál (fechas, predeterminado, hora de Ecuador); la tienda solo lo reproduce. El mantenimiento está en
// ecommerce/VideosInicioController (CRM), que borra la caché al guardar.
class VideosInicioTiendaController extends Controller
{
    // Se guarda 10 minutos por día (la clave lleva la fecha de Ecuador: al cambiar el día se pregunta de nuevo).
    private const CACHE_TIENDA = 'ecommerce.videos_inicio.hoy.';
    private const SEGUNDOS_CACHE_TIENDA = 600;

    // Dónde se guardan los videos (carpeta ecommerce/videos, NAS o local).
    public const CARPETA = ArchivosPaginaWeb::CARPETA . '/videos';
    private const NOMBRE = '/^[A-Za-z0-9]{40}\.mp4$/';

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::claveCache());
    }

    // Dirección pública de un video (null si no hay).
    public static function urlVideo(?string $nombre): ?string
    {
        return $nombre ? url('api/public/videos-inicio/videos/' . $nombre) : null;
    }

    // GET public/videos-inicio/hoy -> {id, nombre, video} o null si hoy no sale ninguno.
    public function hoy()
    {
        try {
            $video = Cache::remember(self::claveCache(), self::SEGUNDOS_CACHE_TIENDA, function () {
                $v = DB::selectOne('SELECT * FROM ecommerce.fn_video_inicio_hoy()');

                // Cache::remember no guarda null: "sin video" se guarda como false.
                return $v ? [
                    'id' => (int) $v->vin_id,
                    'nombre' => $v->vin_nombre,
                    'video' => self::urlVideo($v->vin_video),
                ] : false;
            });

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Video de inicio de hoy', $video ?: null))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar el video de inicio de la tienda', ['error' => $th->getMessage()]);

            // HTTP 200 como todo el estándar: la tienda ve status 'error' y simplemente no muestra el video.
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el video de inicio', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/videos-inicio/videos/{nombre}: el video, del NAS o de local (ArchivosPaginaWeb::entregar; desde local con
    // soporte de rangos, para que empiece a verse sin descargarlo entero). Cada video nuevo tiene otro nombre: un año en caché.
    public function verVideo(string $nombre)
    {
        if (!preg_match(self::NOMBRE, $nombre)) {
            abort(404);
        }

        return ArchivosPaginaWeb::entregar(self::CARPETA . '/' . $nombre, 'video/mp4');
    }

    private static function claveCache(): string
    {
        return self::CACHE_TIENDA . now('America/Guayaquil')->toDateString();
    }
}
