<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Secciones del inicio de la tienda: banner_principal, categorias_destacadas, ofertas_flash, banner_simple,
 * mosaico, grilla, bloques y marcas. Cada una tiene su interruptor, su orden y su vigencia (desde / hasta);
 * algunas además título o subtítulo. Llama a las funciones ecommerce.fn_inicio_seccion_*.
 * La tienda lee todo el inicio en GET public/pagina-web/inicio (con caché que respeta la vigencia: CachePaginaWeb).
 */
class InicioSeccionService
{
    private const CACHE_PUBLICA = 'pagina_web.inicio.publica';

    // Códigos propios (los CAMPO_* y FECHAS_RANGO comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'SECCION_NO_EXISTE'              => 'La sección no existe.',
        'SECCION_ACTIVO_INVALIDO'        => 'El valor de "mostrar" debe ser verdadero o falso.',
        'SECCIONES_ORDEN_INVALIDO'       => 'El nuevo orden no es válido.',
        'SECCIONES_ORDEN_DESACTUALIZADO' => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.',
    ];

    public function __construct(private BloqueProductoService $bloques, private ProductoTiendaService $productos)
    {
    }

    /** Tras un cambio de una sección, la tienda lo ve al momento. También lo llaman los bloques, las galerías y las categorías. */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_PUBLICA);
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /**
     * [{ise_clave, ise_nombre, ise_orden, ise_activo, ise_titulo, ise_subtitulo,
     *   ise_desde, ise_hasta, vigencia, visible}] en el orden del inicio
     */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_inicio_seccion_listar()');
    }

    /** $datos: solo lo que cambia (ise_activo, ise_titulo, ise_subtitulo, ise_desde, ise_hasta) + usu_id */
    public function modificar(string $clave, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_inicio_seccion_modificar(?, ?::jsonb)', [$clave, $this->json($datos)]);
        self::limpiarCache();
    }

    /** $datos: {claves: [ise_clave…] (todas las secciones, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_inicio_seccion_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * Las secciones visibles (encendidas y vigentes), en su orden, cada una con su clave y sus datos:
     *   {secciones: [
     *     {clave: 'banner_principal', imagenes}, {clave: 'categorias_destacadas', categorias},
     *     {clave: 'ofertas_flash', titulo, subtitulo, hasta, productos}, {clave: 'banner_simple', imagenes},
     *     {clave: 'mosaico', categorias: [{tpr_id, nombre, imagen}]}, {clave: 'grilla', titulo, productos},
     *     {clave: 'bloques', bloques: [{…, productos, promociones}]}, {clave: 'marcas', imagenes}]}
     * productos = tarjetas (ProductoTiendaService::tarjetas). ofertas_flash trae todas las ofertas de la semana y grilla
     * todos los destacados (sin cantidad); las dos salen aunque lleguen con productos: [].
     * Una sección de imágenes o categorías cuya lista llega vacía no se incluye; una clave desconocida se ignora.
     * La caché dura como máximo 10 minutos (CachePaginaWeb::vencimiento): así no dura más que la de productos publicados.
     */
    public function publica(): array
    {
        return Cache::remember(self::CACHE_PUBLICA, fn () => CachePaginaWeb::vencimiento(), function () {
            $secciones = [];

            foreach ($this->listar() as $s) {
                if (!$s->visible) {
                    continue;
                }

                $seccion = $this->datosPublicos($s);
                if ($seccion !== null) {
                    $secciones[] = ['clave' => $s->ise_clave] + $seccion;
                }
            }

            return ['secciones' => $secciones];
        });
    }

    /** La hora de la base, en el mismo formato que las fechas ("YYYY-MM-DDTHH:MM:SS-05:00"). Sin caché: va en cada petición. */
    public function ahora(): string
    {
        return DB::selectOne('SELECT ecommerce.fn_pagina_web_fecha_texto(now()) AS ahora')->ahora;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Los datos de una sección para la tienda; null si su lista llega vacía o la clave no se conoce. */
    private function datosPublicos(object $s): ?array
    {
        switch ($s->ise_clave) {
            case 'banner_principal':
                return $this->siHay('imagenes', $this->imagenes('inicio_banner'));
            case 'categorias_destacadas':
                return $this->siHay('categorias', DB::select('SELECT * FROM ecommerce.fn_categoria_web_listar_publicas()'));
            case 'ofertas_flash':
                return [
                    'titulo'    => $s->ise_titulo,
                    'subtitulo' => $s->ise_subtitulo,
                    'hasta'     => $s->ise_hasta,
                    'productos' => $this->productos->tarjetas(true, false, null, null, null),
                ];
            case 'banner_simple':
                return $this->siHay('imagenes', $this->imagenes('inicio_banner_simple'));
            case 'mosaico':
                return $this->siHay('categorias', DB::select('SELECT * FROM ecommerce.fn_categoria_web_listar_mosaico_publicas()'));
            case 'grilla':
                return [
                    'titulo'    => $s->ise_titulo,
                    'productos' => $this->productos->tarjetas(false, true, null, null, null),
                ];
            case 'bloques':
                return $this->siHay('bloques', $this->bloques->listarPublicos(null));
            case 'marcas':
                return $this->siHay('imagenes', $this->imagenes('marcas_aliadas'));
            default:
                return null;
        }
    }

    /** [$nombre => $lista], o null si la lista está vacía (la sección no se incluye). */
    private function siHay(string $nombre, array $lista): ?array
    {
        return $lista === [] ? null : [$nombre => $lista];
    }

    /** Las imágenes visibles y vigentes de una galería, igual que GET public/pagina-web/galerias/{clave} */
    private function imagenes(string $galClave): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [$galClave]);
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
