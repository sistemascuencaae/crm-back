<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Bloques destacados de productos (título, categoría, cantidad y lado de la imagen).
 * Un bloque es del INICIO (pma_id null: categoría principal obligatoria, productos de todas las marcas, sin galería:
 * su carrusel son tarjetas de promoción de productos) o de una PÁGINA DE MARCA (pma_id: categoría opcional,
 * solo productos de esa marca, con su galería). Llama a las funciones ecommerce.fn_bloque_producto_*.
 * Las imágenes de los bloques de marca se editan con las rutas de galerías.
 */
class BloqueProductoService
{
    // Códigos propios de los bloques (los CAMPO_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'BLOQUE_NO_EXISTE'       => 'El bloque no existe.',
        'BLOQUES_MAXIMO'         => 'Se pueden tener como máximo 10 bloques.',
        'PAGINA_MARCA_NO_EXISTE' => 'La página no existe.',
        'CATEGORIA_NO_EXISTE'    => 'La categoría no existe o está inactiva en el sistema.',
        'CATEGORIA_NO_PRINCIPAL' => 'Elige una categoría principal, por ejemplo CLIMATIZACION o TV Y VIDEO.',
    ];

    public function __construct(private ProductoTiendaService $productos)
    {
    }

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
     * ('siempre' | 'programada' | 'vigente' | 'vencida'), productos_promocion (cuántas tarjetas tendrá su carrusel),
     * categoria_principal (false en bloques viejos con subcategoría) y su galería
     * (igual que un elemento de GET galerias: gal_id, gal_tipo, gal_maximo, total_imagenes…; null en los del inicio).
     */
    public function listar(?int $pmaId): array
    {
        $bloques = DB::select('SELECT * FROM ecommerce.fn_bloque_producto_listar(?)', [$pmaId]);

        foreach ($bloques as $bloque) {
            $bloque->galeria = $bloque->gal_id === null
                ? null
                : DB::selectOne('SELECT * FROM ecommerce.fn_galeria_buscar(?)', [$bloque->gal_id]);
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

    /** Elimina el bloque (y su galería e imágenes, si es de una página de marca). */
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

    /**
     * Los bloques visibles y vigentes, cada uno con sus tarjetas de producto (ProductoTiendaService::tarjetas):
     *   inicio ($pmaId null):  [{titulo, tpr_id, cantidad, imagen_lado, productos: [destacados de su categoría],
     *                            promociones: [{pro_id, marca, nombre, imagen, tpr_id, precio}]}]
     *                          Un bloque del inicio sin productos ni promociones no sale.
     *   página de marca:       [{titulo, tpr_id, cantidad, imagen_lado, productos: [...], imagenes: [...]}]
     * En una página de marca los productos son de esa marca ($marId) y las imágenes siguen saliendo de la galería del bloque.
     */
    public function listarPublicos(?int $pmaId, ?int $marId = null): array
    {
        $bloques = [];

        foreach (DB::select('SELECT * FROM ecommerce.fn_bloque_producto_listar_publicos(?)', [$pmaId]) as $b) {
            $tprId  = $b->tpr_id === null ? null : (int) $b->tpr_id;
            $bloque = [
                'titulo'      => $b->bpr_titulo,
                'tpr_id'      => $tprId,
                'cantidad'    => (int) $b->bpr_cantidad,
                'imagen_lado' => $b->bpr_imagen_lado,
            ];

            if ($pmaId === null) {
                $bloque['productos']   = $this->productos->tarjetas(false, true, $tprId, null, (int) $b->bpr_cantidad);
                $bloque['promociones'] = $this->productos->promociones($tprId);

                if ($bloque['productos'] === [] && $bloque['promociones'] === []) {
                    continue;
                }
            } else {
                $bloque['productos'] = $this->productos->tarjetas(false, false, $tprId, $marId, (int) $b->bpr_cantidad);
                $bloque['imagenes']  = DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [$b->gal_clave]);
            }

            $bloques[] = $bloque;
        }

        return $bloques;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}