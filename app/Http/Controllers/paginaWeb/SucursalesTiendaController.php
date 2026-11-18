<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Nuestras tiendas en la tienda (público, sin login): ciudades y sucursales activas (ecommerce.fn_sucursal_listar_publicas).
// El mantenimiento está en ecommerce/SucursalesController (CRM), que borra la caché al guardar.
class SucursalesTiendaController extends Controller
{
    // Lo que lee la tienda se guarda 10 minutos; cada cambio desde el CRM lo borra (limpiarCache).
    private const CACHE_TIENDA = 'ecommerce.sucursales.tienda';
    private const SEGUNDOS_CACHE_TIENDA = 600;

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // GET public/sucursales/tienda -> [{sci_id, sci_nombre, sucursales: [{suc_id, suc_nombre, suc_direccion, suc_horario,
    // suc_telefono, suc_latitud, suc_longitud}]}]: solo ciudades y sucursales activas, en su orden.
    public function tienda()
    {
        try {
            $ciudades = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                return collect(DB::select('SELECT * FROM ecommerce.fn_sucursal_listar_publicas()'))
                    ->groupBy('sci_id')
                    ->map(fn ($grupo) => [
                        'sci_id' => $grupo[0]->sci_id,
                        'sci_nombre' => $grupo[0]->sci_nombre,
                        'sucursales' => $grupo->map(fn ($s) => [
                            'suc_id' => $s->suc_id,
                            'suc_nombre' => $s->suc_nombre,
                            'suc_direccion' => $s->suc_direccion,
                            'suc_horario' => $s->suc_horario,
                            'suc_telefono' => $s->suc_telefono,
                            'suc_latitud' => $s->suc_latitud === null ? null : (float) $s->suc_latitud,
                            'suc_longitud' => $s->suc_longitud === null ? null : (float) $s->suc_longitud,
                        ])->values()->all(),
                    ])
                    ->values()
                    ->all();
            });

            // no-cache: el navegador siempre pregunta, así los cambios del CRM se ven al instante.
            return response()
                ->json(RespuestaApi::returnResultado('success', 'Sucursales', $ciudades))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron listar las sucursales de la tienda', ['error' => $th->getMessage()]);

            // HTTP 500 a propósito: la tienda trata la falla como error y el navegador no la guarda.
            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las tiendas', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}
