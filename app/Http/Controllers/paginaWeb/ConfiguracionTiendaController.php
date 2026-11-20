<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Configuración de la tienda (público, sin login): el número de WhatsApp (ecommerce.fn_configuracion_tienda_publica).
// La publicidad y el video de inicio tienen sus propios endpoints (VentanasEmergentesTiendaController y
// VideosInicioTiendaController). El mantenimiento está en ecommerce/ConfiguracionController (CRM), que borra la caché
// al guardar.
class ConfiguracionTiendaController extends Controller
{
    // Lo que lee la tienda se guarda 10 minutos; cada cambio desde el CRM lo borra (limpiarCache).
    private const CACHE_TIENDA = 'ecommerce.configuracion.tienda';
    private const SEGUNDOS_CACHE_TIENDA = 600;

    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // GET public/configuracion/tienda -> {whatsapp_numero}. null = sin botones de WhatsApp.
    public function tienda()
    {
        try {
            $configuracion = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $c = DB::selectOne('SELECT * FROM ecommerce.fn_configuracion_tienda_publica()');

                return [
                    'whatsapp_numero' => $c->whatsapp_numero ?? null,
                ];
            });

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Configuración de la tienda', $configuracion))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar la configuración de la tienda', ['error' => $th->getMessage()]);

            // HTTP 200 como todo el estándar: la tienda ve status 'error' (sin botones de WhatsApp).
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la configuración', null))
                ->header('Cache-Control', 'no-store');
        }
    }
}
