<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Secciones del inicio de la tienda, en orden: banner_principal, categorias_destacadas, grilla, bloques y marcas.
 * Cada una tiene su interruptor; la grilla además tiene título y cantidad.
 * Llama a las funciones ecommerce.fn_inicio_seccion_*.
 * El banner y las categorías destacadas tienen sus propios endpoints públicos; la grilla, los bloques
 * y las marcas van en GET public/pagina-web/inicio (con caché).
 */
class InicioSeccionService
{
    private const CACHE_PUBLICA = 'pagina_web.inicio.publica';
    private const CACHE_MINUTOS = 10;

    // Códigos propios (los CAMPO_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'SECCION_NO_EXISTE'       => 'La sección no existe.',
        'SECCION_ACTIVO_INVALIDO' => 'El valor de "mostrar" debe ser verdadero o falso.',
    ];

    public function __construct(private BloqueProductoService $bloques)
    {
    }

    /** Lo llaman los bloques, las galerías y las categorías después de cada cambio. */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_PUBLICA);
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /** [{ise_clave, ise_nombre, ise_activo, ise_titulo, ise_cantidad}] en el orden del inicio */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_inicio_seccion_listar()');
    }

    /** $datos: solo lo que cambia (ise_activo; en la grilla también ise_titulo e ise_cantidad) + usu_id */
    public function modificar(string $clave, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_inicio_seccion_modificar(?, ?::jsonb)', [$clave, json_encode($datos, JSON_UNESCAPED_UNICODE)]);

        // Lo que ve la tienda depende de estos interruptores: debe ver el cambio al momento
        CategoriaWebService::limpiarCache();
        GaleriaService::limpiarCacheInicioBanner();
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * Grilla, bloques y marcas del inicio. Una sección apagada llega vacía ([] o null):
     *   {grilla: {titulo, cantidad} | null,
     *    bloques: [{titulo, tpr_id, cantidad, imagen_lado, imagenes: [...]}],
     *    marcas: [imágenes de marcas_aliadas, con gim_enlace]}
     */
    public function publica(): array
    {
        return Cache::remember(self::CACHE_PUBLICA, now()->addMinutes(self::CACHE_MINUTOS), function () {
            $secciones = [];
            foreach ($this->listar() as $s) {
                $secciones[$s->ise_clave] = $s;
            }

            $activa = fn (string $clave) => isset($secciones[$clave]) && $secciones[$clave]->ise_activo;

            return [
                'grilla'  => $activa('grilla')
                    ? ['titulo' => $secciones['grilla']->ise_titulo, 'cantidad' => $secciones['grilla']->ise_cantidad]
                    : null,
                'bloques' => $activa('bloques') ? $this->bloques->listarPublicos(null) : [],
                'marcas'  => $activa('marcas')
                    ? DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', ['marcas_aliadas'])
                    : [],
            ];
        });
    }
}