<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Bloques destacados de productos (título, categoría, cantidad, lado de la imagen y su galería).
 * Un bloque es del INICIO (pma_id null: categoría obligatoria, productos de todas las marcas)
 * o de una PÁGINA DE MARCA (pma_id: categoría opcional, solo productos de esa marca).
 * Llama a las funciones ecommerce.fn_bloque_producto_*. Las imágenes se editan con las rutas de galerías.
 */
class BloqueProductoService
{
    // Códigos propios de los bloques (los CAMPO_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'BLOQUE_NO_EXISTE'       => 'El bloque no existe.',
        'BLOQUES_MAXIMO'         => 'Se pueden tener como máximo 10 bloques.',
        'PAGINA_MARCA_NO_EXISTE' => 'La página no existe.',
        'CATEGORIA_NO_EXISTE'    => 'La categoría no existe o está inactiva en el sistema.',
    ];

    /** Lo que ve la tienda depende de los bloques: el inicio y las páginas de marca. */
    public static function limpiarCache(): void
    {
        InicioSeccionService::limpiarCache();
        PaginaMarcaService::limpiarCache();
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /**
     * Los bloques del inicio ($pmaId null) o de una página, en orden, con bpr_desde, bpr_hasta y vigencia
     * ('siempre' | 'programada' | 'vigente' | 'vencida'), cada uno con su galería
     * (igual que un elemento de GET galerias: gal_id, gal_tipo, gal_maximo, total_imagenes…).
     */
    public function listar(?int $pmaId): array
    {
        $bloques = DB::select('SELECT * FROM ecommerce.fn_bloque_producto_listar(?)', [$pmaId]);

        foreach ($bloques as $bloque) {
            $bloque->galeria = DB::selectOne('SELECT * FROM ecommerce.fn_galeria_buscar(?)', [$bloque->gal_id]);
        }

        return $bloques;
    }

    /** $datos: {pma_id (null = inicio), bpr_titulo, tpr_id, bpr_cantidad, bpr_activo, bpr_imagen_lado, bpr_desde, bpr_hasta, usu_id}. Devuelve su id. */
    public function crear(array $datos): int
    {
        $bprId = DB::selectOne('SELECT ecommerce.fn_bloque_producto_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        self::limpiarCache();

        return $bprId;
    }

    /** $datos: solo lo que cambia + usu_id (el dueño no cambia) */
    public function modificar(int $bprId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_modificar(?, ?::jsonb)', [$bprId, $this->json($datos)]);
        self::limpiarCache();
    }

    /** Elimina el bloque con su galería e imágenes. */
    public function eliminar(int $bprId): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_eliminar(?)', [$bprId]);
        self::limpiarCache();
    }

    /** $datos: {pma_id (null = inicio), ids: [bpr_id…] (todos los bloques de ese dueño, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda (lo usan InicioSeccionService y PaginaMarcaService, que ya tienen su caché)
    // -----------------------------------------------------------------

    /** Los bloques visibles y vigentes: [{titulo, tpr_id, cantidad, imagen_lado, imagenes: [...]}] */
    public function listarPublicos(?int $pmaId): array
    {
        $bloques = [];

        foreach (DB::select('SELECT * FROM ecommerce.fn_bloque_producto_listar_publicos(?)', [$pmaId]) as $b) {
            $bloques[] = [
                'titulo'      => $b->bpr_titulo,
                'tpr_id'      => $b->tpr_id,
                'cantidad'    => $b->bpr_cantidad,
                'imagen_lado' => $b->bpr_imagen_lado,
                'imagenes'    => DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [$b->gal_clave]),
            ];
        }

        return $bloques;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}