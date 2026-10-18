<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lo que ve la tienda del header y del footer, en un solo JSON (con caché de 10 minutos).
 * Las funciones de la base devuelven filas; aquí se arma el mismo JSON que la tienda ya usa:
 *   [ {clave: 'header', config, menu: [...], grupos: []},
 *     {clave: 'footer', config, menu: [],    grupos: [{titulo, items: [...]}]} ]
 * Solo van los enlaces, columnas y redes activos.
 */
class EncabezadoPiePublicoService
{
    public const CACHE_CLAVE = 'pagina_web.encabezado_pie.publico';

    /** Lo llaman EncabezadoService y PiePaginaService después de cada cambio. */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_CLAVE);
    }

    public function listar(): array
    {
        return Cache::remember(self::CACHE_CLAVE, now()->addMinutes(10), function () {
            return [$this->encabezado(), $this->piePagina()];
        });
    }

    private function encabezado(): array
    {
        $fila = DB::selectOne('SELECT * FROM ecommerce.fn_encabezado_buscar()');

        $menu = [];
        foreach (DB::select('SELECT * FROM ecommerce.fn_encabezado_enlace_listar()') as $enlace) {
            if ($enlace->een_activo) {
                $menu[] = $this->enlace($enlace->een_texto, $enlace->een_enlace, $enlace->een_icono, $enlace->een_nueva_pestana);
            }
        }

        // clave, tipo, ubicacion, orden y titulo se mantienen como antes para que la tienda no cambie
        return [
            'clave'     => 'header',
            'tipo'      => 'header',
            'ubicacion' => 'arriba',
            'orden'     => 1,
            'titulo'    => 'Header',
            'config'    => $fila ? json_decode($fila->enc_config) : new \stdClass(),
            'menu'      => $menu,
            'grupos'    => [],
        ];
    }

    private function piePagina(): array
    {
        $fila   = DB::selectOne('SELECT * FROM ecommerce.fn_pie_pagina_buscar()');
        $config = $fila ? json_decode($fila->pie_config) : new \stdClass();

        // Redes: solo las activas, con tipo y enlace
        $redes = [];
        foreach ($config->redes ?? [] as $red) {
            if ($red->activo ?? true) {
                $redes[] = ['tipo' => $red->tipo ?? null, 'enlace' => $red->enlace ?? null];
            }
        }
        $config->redes = $redes;

        $enlaces = DB::select('SELECT * FROM ecommerce.fn_pie_enlace_listar()');

        // Columnas activas, cada una con sus enlaces activos
        $grupos = [];
        foreach (DB::select('SELECT * FROM ecommerce.fn_pie_columna_listar()') as $columna) {
            if (!$columna->pco_activo) {
                continue;
            }

            $items = [];
            foreach ($enlaces as $enlace) {
                if ($enlace->pco_id === $columna->pco_id && $enlace->pen_activo) {
                    $items[] = $this->enlace($enlace->pen_texto, $enlace->pen_enlace, $enlace->pen_icono, $enlace->pen_nueva_pestana);
                }
            }

            $grupos[] = ['titulo' => $columna->pco_titulo, 'items' => $items];
        }

        return [
            'clave'     => 'footer',
            'tipo'      => 'footer',
            'ubicacion' => 'abajo',
            'orden'     => 1,
            'titulo'    => 'Footer',
            'config'    => $config,
            'menu'      => [],
            'grupos'    => $grupos,
        ];
    }

    private function enlace(string $texto, ?string $enlace, ?string $icono, bool $nuevaPestana): array
    {
        return ['texto' => $texto, 'enlace' => $enlace, 'icono' => $icono, 'nueva_pestana' => $nuevaPestana];
    }
}