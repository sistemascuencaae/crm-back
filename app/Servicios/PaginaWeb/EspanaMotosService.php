<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla España Motos: una sola configuración (banner, fondo con título, pestañas de categorías,
 * marcas con su logo y tarjetas). Llama a las funciones ecommerce.fn_espana_motos_*.
 * Las imágenes del banner y de las tarjetas se editan con las rutas de galerías.
 * La tienda la lee con caché que respeta la vigencia (CachePaginaWeb).
 */
class EspanaMotosService
{
    private const CACHE_PUBLICA = 'pagina_web.espana_motos.publica';

    // Códigos propios (los CAMPO_* y ORDEN_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'MOTOS_PESTANA_NO_EXISTE' => 'La pestaña no existe.',
        'MOTOS_PESTANAS_MAXIMO'   => 'Se pueden tener como máximo 6 pestañas.',
        'MOTOS_MARCA_NO_EXISTE'   => 'La marca no está en la pantalla.',
        'MOTOS_MARCAS_MAXIMO'     => 'Se pueden tener como máximo 30 marcas.',
        'MOTOS_MARCA_REPETIDA'    => 'Esa marca ya está en la pantalla.',
        'MARCA_NO_EXISTE'         => 'La marca no existe o está inactiva en el sistema.',
        'CATEGORIA_NO_EXISTE'     => 'Alguna categoría no existe o está inactiva en el sistema.',
        'CAMPO_CATEGORIAS'        => 'Elige de 1 a 5 categorías, sin repetir.',
        'CAMPO_IMAGEN'            => 'La dirección de la imagen no es válida.',
    ];

    /** Tras un cambio, la tienda debe ver lo nuevo. También lo llaman GaleriaService y CategoriaWebService. */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_PUBLICA);
    }

    // -----------------------------------------------------------------
    // CRM: configuración
    // -----------------------------------------------------------------

    /**
     * Todo lo que necesita el editor:
     *   {…configuración, galerias: {banner, tarjetas}, pestanas: [...], marcas: [...]}
     * Cada galería viene como en GET galerias (gal_id, gal_tipo, gal_maximo, total_imagenes…).
     */
    public function buscar(): object
    {
        $pantalla = DB::selectOne('SELECT * FROM ecommerce.fn_espana_motos_buscar()');

        $pantalla->galerias = [
            'banner'   => $this->galeria($pantalla->gal_banner_id),
            'tarjetas' => $this->galeria($pantalla->gal_tarjetas_id),
        ];

        $pantalla->pestanas = DB::select('SELECT * FROM ecommerce.fn_espana_motos_pestana_listar()');
        foreach ($pantalla->pestanas as $pestana) {
            $pestana->emp_categorias = $this->enteros($pestana->emp_categorias);
        }

        $pantalla->marcas = DB::select('SELECT * FROM ecommerce.fn_espana_motos_marca_listar()');

        return $pantalla;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_modificar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    /** Marcas activas del ERP para agregar: [{mar_id, mar_nombre, productos_motos, agregada}] */
    public function marcasErp(string $buscar): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_espana_motos_marcas_erp(?)', [$buscar]);
    }

    // -----------------------------------------------------------------
    // CRM: pestañas
    // -----------------------------------------------------------------

    /** $datos: {emp_nombre, emp_categorias: [tpr_id…], emp_activo, usu_id}. Devuelve su id. */
    public function crearPestana(array $datos): int
    {
        $empId = DB::selectOne('SELECT ecommerce.fn_espana_motos_pestana_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        self::limpiarCache();

        return $empId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificarPestana(int $empId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_pestana_modificar(?, ?::jsonb)', [$empId, $this->json($datos)]);
        self::limpiarCache();
    }

    public function eliminarPestana(int $empId): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_pestana_eliminar(?)', [$empId]);
        self::limpiarCache();
    }

    /** $datos: {ids: [emp_id…] (todas las pestañas, en el nuevo orden), usu_id} */
    public function ordenarPestanas(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_pestana_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // CRM: marcas
    // -----------------------------------------------------------------

    /** $datos: {mar_id, emm_logo, emm_activo, usu_id}. Devuelve su id. */
    public function crearMarca(array $datos): int
    {
        $emmId = DB::selectOne('SELECT ecommerce.fn_espana_motos_marca_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        self::limpiarCache();

        return $emmId;
    }

    /** $datos: solo lo que cambia (emm_logo, emm_activo) + usu_id; la marca no se cambia */
    public function modificarMarca(int $emmId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_marca_modificar(?, ?::jsonb)', [$emmId, $this->json($datos)]);
        self::limpiarCache();
    }

    public function eliminarMarca(int $emmId): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_marca_eliminar(?)', [$emmId]);
        self::limpiarCache();
    }

    /** $datos: {ids: [emm_id…] (todas las marcas, en el nuevo orden), usu_id} */
    public function ordenarMarcas(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_espana_motos_marca_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * La pantalla publicada, lista para la tienda, o null si no está publicada.
     * Una sección apagada llega vacía ([] o null):
     *   {banner: [imágenes], fondo: {imagen, titulo, subtitulo} | null,
     *    pestanas: [{nombre, categorias: [tpr_id…]}],
     *    categorias: [tpr_id…]   todas las de las pestañas, sin repetir, en el orden en que aparecen,
     *    marcas: [{mar_id, nombre, logo}], tarjetas: [imágenes]}
     */
    public function publica(): ?array
    {
        return Cache::remember(self::CACHE_PUBLICA, fn () => CachePaginaWeb::vencimiento(), function () {
            $p = DB::selectOne('SELECT * FROM ecommerce.fn_espana_motos_buscar_publica()');

            if ($p === null) {
                return null;
            }

            $pestanas   = [];
            $categorias = [];
            foreach (DB::select('SELECT * FROM ecommerce.fn_espana_motos_pestana_listar_publicas()') as $fila) {
                $ids        = $this->enteros($fila->emp_categorias);
                $pestanas[] = ['nombre' => $fila->emp_nombre, 'categorias' => $ids];
                $categorias = array_merge($categorias, $ids);
            }

            $marcas = array_map(fn ($m) => [
                'mar_id' => (int) $m->mar_id,
                'nombre' => $m->mar_nombre,
                'logo'   => $m->emm_logo,
            ], DB::select('SELECT * FROM ecommerce.fn_espana_motos_marca_listar_publicas()'));

            return [
                'banner'     => $p->emo_banner_visible ? $this->imagenes($p->banner_clave) : [],
                'fondo'      => $p->emo_fondo_visible
                    ? ['imagen' => $p->emo_fondo_imagen, 'titulo' => $p->emo_fondo_titulo, 'subtitulo' => $p->emo_fondo_subtitulo]
                    : null,
                'pestanas'   => $pestanas,
                'categorias' => array_values(array_unique($categorias)),
                'marcas'     => $marcas,
                'tarjetas'   => $p->emo_tarjetas_visible ? $this->imagenes($p->tarjetas_clave) : [],
            ];
        });
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Un arreglo de Postgres ("{416,420}") como lista de números: [416, 420] */
    private function enteros(?string $arreglo): array
    {
        $texto = trim((string) $arreglo, '{}');

        return $texto === '' ? [] : array_map('intval', explode(',', $texto));
    }

    private function galeria(int $galId): ?object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_galeria_buscar(?)', [$galId]);
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
