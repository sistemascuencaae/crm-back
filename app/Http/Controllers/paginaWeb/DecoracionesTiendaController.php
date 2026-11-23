<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Decoración de temporada del inicio de la tienda (público, sin login): la que toca hoy (ecommerce.fn_decoracion_hoy).
// La base decide cuál (fechas, prioridad, hora de Ecuador); la tienda solo la dibuja. El mantenimiento está en
// ecommerce/DecoracionesController (CRM), que borra la caché al guardar.
class DecoracionesTiendaController extends Controller
{
    // Se guarda 10 minutos por día (la clave lleva la fecha de Ecuador: al cambiar el día se pregunta de nuevo).
    private const CACHE_TIENDA = 'ecommerce.decoraciones.hoy.';
    private const SEGUNDOS_CACHE_TIENDA = 600;

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::claveCache());
    }

    // GET public/decoraciones/hoy -> {efecto, intensidad, segundos, clave} o null si hoy no hay decoración.
    //   segundos: null = todo el tiempo; clave: para recordar que el visitante la cerró con la ✕.
    public function hoy()
    {
        try {
            $decoracion = Cache::remember(self::claveCache(), self::SEGUNDOS_CACHE_TIENDA, function () {
                $d = DB::selectOne('SELECT * FROM ecommerce.fn_decoracion_hoy()');

                // Cache::remember no guarda null: "sin decoración" se guarda como false.
                return $d ? [
                    'efecto' => $d->dec_efecto,
                    'intensidad' => $d->dec_intensidad,
                    'segundos' => $d->dec_segundos === null ? null : (int) $d->dec_segundos,
                    'clave' => $d->clave,
                ] : false;
            });

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Decoración de hoy', $decoracion ?: null))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la decoración de la tienda', ['error' => $th->getMessage()]);

            // HTTP 200 como todo el estándar: la tienda ve status 'error' y simplemente no decora.
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la decoración', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    private static function claveCache(): string
    {
        return self::CACHE_TIENDA . now('America/Guayaquil')->toDateString();
    }
}
