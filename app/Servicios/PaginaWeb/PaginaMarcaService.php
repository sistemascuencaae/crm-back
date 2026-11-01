<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Páginas de marca: una por marca del ERP, con la misma plantilla fija (banner, destacados, todos los productos,
 * fotos y contacto). Llama a las funciones ecommerce.fn_pagina_marca_*.
 * Las imágenes del banner y de las fotos se editan con las rutas de galerías; los productos salen solos de sus marcas.
 * La tienda lee cada página por su dirección, con caché que respeta la vigencia (CachePaginaWeb).
 */
class PaginaMarcaService
{
    private const CACHE_PREFIJO = 'pagina_web.marca.publica.';

    public function __construct(private ProductoTiendaService $productos)
    {
    }

    // Códigos propios de las páginas de marca (los CAMPO_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'MARCA_NO_EXISTE'                 => 'La marca no existe o está inactiva en el sistema.',
        'MARCA_CON_PAGINA'                => 'Esa marca ya tiene su página.',
        'PAGINA_MARCA_NO_EXISTE'          => 'La página no existe.',
        'PAGINA_MARCA_DIRECCION_REPETIDA' => 'Otra página ya usa esta dirección.',
        'CAMPO_DIRECCION'                 => 'Usa solo minúsculas, números y guiones (por ejemplo: mach-potence).',
    ];

    /** Tras un cambio, la tienda debe ver lo nuevo. También lo llama GaleriaService al cambiar una imagen. */
    public static function limpiarCache(?string $direccionAnterior = null): void
    {
        if ($direccionAnterior !== null) {
            Cache::forget(self::CACHE_PREFIJO . $direccionAnterior);
        }

        foreach (DB::select('SELECT pma_direccion FROM ecommerce.pagina_marca') as $fila) {
            Cache::forget(self::CACHE_PREFIJO . $fila->pma_direccion);
        }
    }

    // -----------------------------------------------------------------
    // CRM: páginas
    // -----------------------------------------------------------------

    /** Marcas activas del ERP para crear una página (máximo 20): [{mar_id, mar_nombre, mar_padre, con_pagina}] */
    public function marcasErp(string $buscar): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_pagina_marca_marcas_erp(?)', [$buscar]);
    }

    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_pagina_marca_listar()');
    }

    /**
     * La página con lo que necesita el editor, o null si no existe:
     *   {…campos de la página, galerias: {banner, fotos}}
     * Cada galería viene como en GET galerias (gal_id, gal_tipo, gal_maximo, total_imagenes…).
     */
    public function buscar(int $pmaId): ?object
    {
        $pagina = DB::selectOne('SELECT * FROM ecommerce.fn_pagina_marca_buscar(?)', [$pmaId]);

        if ($pagina === null) {
            return null;
        }

        $pagina->galerias = [
            'banner' => $this->galeria($pagina->gal_banner_id),
            'fotos'  => $this->galeria($pagina->gal_fotos_id),
        ];

        return $pagina;
    }

    /** $datos: {mar_id, usu_id}. Devuelve el id de la página nueva (oculta). */
    public function crear(array $datos): int
    {
        $pmaId = DB::selectOne('SELECT ecommerce.fn_pagina_marca_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        self::limpiarCachePaginaCambiada();

        return $pmaId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificar(int $pmaId, array $datos): void
    {
        $anterior = DB::selectOne('SELECT pma_direccion FROM ecommerce.pagina_marca WHERE pma_id = ?', [$pmaId]);

        DB::statement('SELECT ecommerce.fn_pagina_marca_modificar(?, ?::jsonb)', [$pmaId, $this->json($datos)]);
        self::limpiarCachePaginaCambiada($anterior->pma_direccion ?? null);
    }

    /** La manda a la papelera (sus galerías e imágenes se borran al vaciar) */
    public function eliminar(int $pmaId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_marca_eliminar(?, ?)', [$pmaId, $usuId]);
        self::limpiarCachePaginaCambiada();
    }

    /** La saca de la papelera; vuelve oculta (pma_publicada = false) */
    public function restaurar(int $pmaId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_marca_restaurar(?, ?)', [$pmaId, $usuId]);
        self::limpiarCachePaginaCambiada();
    }

    /** [{pma_id, mar_nombre, pma_titulo, pma_direccion, pma_eliminado_en, pma_eliminado_usu_id, eliminado_por}] */
    public function papelera(): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_pagina_marca_papelera()'), 'pma_eliminado_usu_id');
    }

    /** Borra para siempre las páginas de la papelera, con sus galerías y archivos. Devuelve cuántas páginas borró. */
    public function vaciar(int $usuId): int
    {
        // La base devuelve los archivos de sus galerías, no las páginas: se cuentan antes
        $cantidad = count(DB::select('SELECT pma_id FROM ecommerce.fn_pagina_marca_papelera()'));

        ArchivosPaginaWeb::borrar(array_column(DB::select('SELECT * FROM ecommerce.fn_pagina_marca_vaciar(?)', [$usuId]), 'imagen'));
        self::limpiarCachePaginaCambiada();

        return $cantidad;
    }

    /**
     * Crear, modificar, eliminar, restaurar o vaciar una página: además de las páginas de marca, cambia el enlace de su logo
     * en "Nuestras marcas" (página publicada → /marca/{direccion}; si no → /shop?marca={id}).
     */
    private static function limpiarCachePaginaCambiada(?string $direccionAnterior = null): void
    {
        self::limpiarCache($direccionAnterior);
        MarcaWebService::limpiarCacheNuestrasMarcas();
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * La página publicada, lista para la tienda, o null si no existe o está oculta.
     * Una sección apagada llega vacía ([] o null), así la tienda solo pinta lo que viene. Los productos salen de la marca
     * y sus sub-marcas (fn_pagina_marca_marcas_incluidas), de cualquier categoría; los títulos los pone la tienda:
     *   {direccion, titulo, logo (el de la marca), mar_id,
     *    banner: [imágenes],
     *    destacados: {productos: [tarjetas destacadas], promociones: [{pro_id, marca, nombre, imagen, tpr_id, precio}]},
     *    productos: [tarjetas: todos los publicados de sus marcas],
     *    fotos: [imágenes],
     *    contacto: {titulo, texto, boton_texto, boton_enlace, boton_nueva_pestana} | null}
     */
    public function publica(string $direccion): ?array
    {
        return Cache::remember(self::CACHE_PREFIJO . $direccion, fn () => CachePaginaWeb::vencimiento(), function () use ($direccion) {
            $p = DB::selectOne('SELECT * FROM ecommerce.fn_pagina_marca_buscar_publica(?)', [$direccion]);

            if ($p === null) {
                return null;
            }

            // La marca y sus sub-marcas: de ahí salen los destacados, las promociones y los productos
            $marcas = array_map(fn ($m) => (int) $m->mar_id,
                                DB::select('SELECT * FROM ecommerce.fn_pagina_marca_marcas_incluidas(?)', [$p->mar_id]));

            return [
                'direccion'  => $p->pma_direccion,
                'titulo'     => $p->pma_titulo,
                'logo'       => $p->pma_logo,
                'mar_id'     => (int) $p->mar_id,
                'banner'     => $p->pma_banner_visible ? $this->imagenes($p->banner_clave) : [],
                'destacados' => [
                    'productos'   => $this->productos->tarjetas(false, true, null, $marcas, null),
                    'promociones' => $this->productos->promociones(null, $marcas),
                ],
                'productos'  => $this->productos->tarjetas(false, false, null, $marcas, null),
                'fotos'      => $p->pma_fotos_visible ? $this->imagenes($p->fotos_clave) : [],
                'contacto'   => $p->pma_contacto_visible
                    ? [
                        'titulo'              => $p->pma_contacto_titulo,
                        'texto'               => $p->pma_contacto_texto,
                        'boton_texto'         => $p->pma_contacto_boton_texto,
                        'boton_enlace'        => $p->pma_contacto_boton_enlace,
                        'boton_nueva_pestana' => $p->pma_contacto_nueva_pestana,
                    ]
                    : null,
            ];
        });
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function galeria(int $galId): ?object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_galeria_buscar(?)', [$galId]);
    }

    /** Las imágenes visibles de una galería, igual que GET public/pagina-web/galerias/{clave} */
    private function imagenes(string $galClave): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [$galClave]);
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}