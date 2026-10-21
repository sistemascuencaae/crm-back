<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Secciones del inicio de la tienda: banner_principal, categorias_destacadas, ofertas_flash, banner_simple,
 * mosaico, grilla, bloques y marcas. Cada una tiene su interruptor, su orden y su vigencia (desde / hasta);
 * algunas además título, subtítulo o cantidad. Llama a las funciones ecommerce.fn_inicio_seccion_*.
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

    /**
     * [{ise_clave, ise_nombre, ise_orden, ise_activo, ise_titulo, ise_subtitulo, ise_cantidad,
     *   ise_desde, ise_hasta, vigencia, visible}] en el orden del inicio
     */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_inicio_seccion_listar()');
    }

    /** $datos: solo lo que cambia (ise_activo, ise_titulo, ise_subtitulo, ise_cantidad, ise_desde, ise_hasta) + usu_id */
    public function modificar(string $clave, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_inicio_seccion_modificar(?, ?::jsonb)', [$clave, $this->json($datos)]);
        $this->limpiarCachesTienda();
    }

    /** $datos: {claves: [ise_clave…] (todas las secciones, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_inicio_seccion_ordenar(?::jsonb)', [$this->json($datos)]);
        $this->limpiarCachesTienda();
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * Las secciones visibles (encendidas y vigentes), en su orden, cada una con su clave y sus datos:
     *   {secciones: [
     *     {clave: 'banner_principal', imagenes}, {clave: 'categorias_destacadas', categorias},
     *     {clave: 'ofertas_flash', titulo, subtitulo, cantidad, hasta}, {clave: 'banner_simple', imagenes},
     *     {clave: 'mosaico', tarjetas}, {clave: 'grilla', titulo, cantidad}, {clave: 'bloques', bloques},
     *     {clave: 'marcas', imagenes}]}
     * Una sección cuya lista llega vacía no se incluye; una clave desconocida se ignora.
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
                    'cantidad'  => $s->ise_cantidad,
                    'hasta'     => $s->ise_hasta,
                ];
            case 'banner_simple':
                return $this->siHay('imagenes', $this->imagenes('inicio_banner_simple'));
            case 'mosaico':
                return $this->siHay('tarjetas', $this->imagenes('inicio_mosaico'));
            case 'grilla':
                return ['titulo' => $s->ise_titulo, 'cantidad' => $s->ise_cantidad];
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

    /** Lo que ve la tienda depende de estas secciones: debe ver el cambio al momento. */
    private function limpiarCachesTienda(): void
    {
        CategoriaWebService::limpiarCache();
        GaleriaService::limpiarCacheInicioBanner();
        self::limpiarCache();
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
