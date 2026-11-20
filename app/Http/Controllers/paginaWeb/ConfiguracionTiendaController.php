<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Configuración de la tienda (público, sin login): número de WhatsApp, ventana emergente y video de intro del inicio
// (ecommerce.fn_configuracion_tienda_publica), y los archivos de la ventana y del video.
// El mantenimiento está en ecommerce/ConfiguracionController (CRM), que borra la caché al guardar.
class ConfiguracionTiendaController extends Controller
{
    // Lo que lee la tienda se guarda 10 minutos; cada cambio desde el CRM lo borra (limpiarCache).
    private const CACHE_TIENDA = 'ecommerce.configuracion.tienda';
    private const SEGUNDOS_CACHE_TIENDA = 600;

    // Dónde se guardan la imagen de la ventana y el video (ecommerce/configuracion, NAS o local), y qué tipos hay.
    public const CARPETA = ArchivosPaginaWeb::CARPETA . '/configuracion';
    public const TIPOS = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'mp4' => 'video/mp4'];
    private const NOMBRE = '/^[A-Za-z0-9]{40}\.(webp|jpg|png|mp4)$/';

    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // Dirección pública de un archivo (null si no hay).
    public static function urlArchivo(?string $nombre): ?string
    {
        return $nombre ? url('api/public/configuracion/archivos/' . $nombre) : null;
    }

    // El CRM reemplazó o quitó la imagen o el video: el archivo anterior ya no se usa.
    public static function borrarArchivo(string $nombre): void
    {
        if (preg_match(self::NOMBRE, $nombre)) {
            ArchivosPaginaWeb::borrarRuta(self::CARPETA . '/' . $nombre);
        }
    }

    // GET public/configuracion/tienda -> {whatsapp_numero, ventana_imagen | null, intro_video | null}.
    // La ventana y el video solo vienen si están encendidos en el CRM; whatsapp_numero null = sin botones de WhatsApp.
    public function tienda()
    {
        try {
            $configuracion = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $c = DB::selectOne('SELECT * FROM ecommerce.fn_configuracion_tienda_publica()');

                return [
                    'whatsapp_numero' => $c->whatsapp_numero ?? null,
                    'ventana_imagen' => self::urlArchivo($c->ventana_imagen ?? null),
                    'intro_video' => self::urlArchivo($c->intro_video ?? null),
                ];
            });

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Configuración de la tienda', $configuracion))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la configuración de la tienda', ['error' => $th->getMessage()]);

            // HTTP 200 como todo el estándar: la tienda ve status 'error' (sin WhatsApp, ventana ni video).
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la configuración', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/configuracion/archivos/{nombre}: la imagen de la ventana o el video, del NAS o de local
    // (ArchivosPaginaWeb::entregar; desde local con soporte de rangos). Cada archivo nuevo tiene otro nombre: un año en caché.
    public function verArchivo(string $nombre)
    {
        if (!preg_match(self::NOMBRE, $nombre, $m)) {
            abort(404);
        }

        return ArchivosPaginaWeb::entregar(self::CARPETA . '/' . $nombre, self::TIPOS[$m[1]]);
    }
}
