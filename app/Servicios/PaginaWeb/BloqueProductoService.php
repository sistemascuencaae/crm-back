<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Bloques destacados del inicio (título, categoría principal y lado de la imagen). Cada bloque muestra los productos
 * destacados de su categoría y un carrusel de tarjetas de promoción. Llama a las funciones ecommerce.fn_bloque_producto_*.
 */
class BloqueProductoService
{
    // Códigos propios de los bloques (los CAMPO_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'BLOQUE_NO_EXISTE'       => 'El bloque no existe.',
        'BLOQUES_MAXIMO'         => 'Se pueden tener como máximo 10 bloques.',
        'CATEGORIA_NO_EXISTE'    => 'La categoría no existe o está inactiva en el sistema.',
        'CATEGORIA_NO_PRINCIPAL' => 'Elige una categoría principal, por ejemplo CLIMATIZACION o TV Y VIDEO.',
    ];

    public function __construct(private ProductoTiendaService $productos)
    {
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /**
     * Los bloques, en orden: [{bpr_id, bpr_titulo, tpr_id, categoria, categoria_en_tienda, categoria_principal,
     * bpr_imagen_lado, bpr_orden, bpr_activo, bpr_desde, bpr_hasta, vigencia ('siempre' | 'programada' | 'vigente' |
     * 'vencida'), productos_promocion (cuántas tarjetas tendrá su carrusel)}]
     */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_bloque_producto_listar()');
    }

    /** $datos: {bpr_titulo, tpr_id, bpr_activo, bpr_imagen_lado, bpr_desde, bpr_hasta, usu_id}. Devuelve su id. */
    public function crear(array $datos): int
    {
        $bprId = DB::selectOne('SELECT ecommerce.fn_bloque_producto_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        InicioSeccionService::limpiarCache();

        return $bprId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificar(int $bprId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_modificar(?, ?::jsonb)', [$bprId, $this->json($datos)]);
        InicioSeccionService::limpiarCache();
    }

    /** Lo manda a la papelera */
    public function eliminar(int $bprId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_eliminar(?, ?)', [$bprId, $usuId]);
        InicioSeccionService::limpiarCache();
    }

    /** Lo saca de la papelera, al final de la lista */
    public function restaurar(int $bprId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_restaurar(?, ?)', [$bprId, $usuId]);
        InicioSeccionService::limpiarCache();
    }

    /** [{bpr_id, bpr_titulo, categoria, bpr_eliminado_en, bpr_eliminado_usu_id, eliminado_por}] */
    public function papelera(): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_bloque_producto_papelera()'), 'bpr_eliminado_usu_id');
    }

    /** Borra para siempre los bloques de la papelera. Devuelve cuántos borró. */
    public function vaciar(int $usuId): int
    {
        $cantidad = (int) DB::selectOne('SELECT ecommerce.fn_bloque_producto_vaciar(?) AS cantidad', [$usuId])->cantidad;
        InicioSeccionService::limpiarCache();

        return $cantidad;
    }

    /** $datos: {ids: [bpr_id…] (todos los bloques, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_bloque_producto_ordenar(?::jsonb)', [$this->json($datos)]);
        InicioSeccionService::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda (lo usa InicioSeccionService, que ya tiene su caché)
    // -----------------------------------------------------------------

    /**
     * Los bloques visibles y vigentes:
     *   [{titulo, tpr_id, imagen_lado, productos: [todos los destacados de su categoría],
     *     promociones: [{pro_id, marca, nombre, imagen, tpr_id, precio}]}]
     * Un bloque sin productos ni promociones no sale.
     */
    public function listarPublicos(): array
    {
        $bloques = [];

        foreach (DB::select('SELECT * FROM ecommerce.fn_bloque_producto_listar_publicos()') as $b) {
            $tprId  = (int) $b->tpr_id;
            $bloque = [
                'titulo'      => $b->bpr_titulo,
                'tpr_id'      => $tprId,
                'imagen_lado' => $b->bpr_imagen_lado,
                'productos'   => $this->productos->tarjetas(false, true, $tprId, null, null),
                'promociones' => $this->productos->promociones($tprId, null),
            ];

            if ($bloque['productos'] !== [] || $bloque['promociones'] !== []) {
                $bloques[] = $bloque;
            }
        }

        return $bloques;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
