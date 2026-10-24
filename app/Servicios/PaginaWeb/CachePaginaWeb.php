<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hasta cuándo dura la caché pública de la tienda: 10 minutos, o menos si antes algo empieza o termina
 * su vigencia (un banner programado, una oferta que vence…). Así la tienda ve el cambio a su hora.
 * Se pasa como closure a Cache::remember (fn () => CachePaginaWeb::vencimiento()) para que la base
 * solo se consulte cuando la caché se vuelve a llenar.
 */
class CachePaginaWeb
{
    private const MINUTOS = 10;

    /** El menor entre "ahora + 10 minutos" y el próximo cambio de vigencia + 1 segundo. */
    public static function vencimiento(): Carbon
    {
        $limite  = now()->addMinutes(self::MINUTOS);
        $proximo = DB::selectOne('SELECT ecommerce.fn_pagina_web_proximo_cambio() AS fecha')->fecha;

        if ($proximo === null) {
            return $limite;
        }

        $cambio = Carbon::parse($proximo)->addSecond();

        return $cambio->lt($limite) ? $cambio : $limite;
    }
}
