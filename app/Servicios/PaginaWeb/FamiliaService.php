<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Familias de productos (variantes): varios SKU que son el mismo producto con distintas variantes (iPhone 18: Negro
 * 128 GB, Rojo 256 GB…). Cada SKU sigue siendo independiente (precio, stock, fotos, ficha). La familia guarda su nombre,
 * su categoría y marca (las del primer producto), las 1 o 2 características de la ficha que cambian, su SKU principal
 * y sus SKU (máximo 30); los valores (Negro, 256…) se leen de la ficha técnica de cada producto.
 * Llama a las funciones inventarios.fn_familia_*. En la tienda: una tarjeta por familia ("Desde $X") y, en el detalle,
 * botones para elegir la variante (ProductoTiendaService).
 */
class FamiliaService
{
    public function __construct(private ProductoTiendaService $productos)
    {
    }

    /** {registros, total} de las familias vigentes; busca en nombre, marca, categoría y nombre o código de sus SKU */
    public function listar(string $busqueda, int $pagina, int $tamanio): array
    {
        return $this->pagina(DB::select('SELECT * FROM inventarios.fn_familia_listar(?, ?, ?)', [$busqueda, $pagina, $tamanio]));
    }

    /**
     * La familia con sus SKU (máximo 30, sin paginar), o null si no existe:
     *   {familia: {…}, productos: [{pro_id, pro_codigo, nombre, imagen, es_principal, valor_1, valor_2, aviso, precio}]}
     * precio: el de la tienda si está publicado; si no, null.
     */
    public function buscar(int $famId): ?array
    {
        $familia = DB::selectOne('SELECT * FROM inventarios.fn_familia_buscar(?)', [$famId]);

        if ($familia === null) {
            return null;
        }

        return [
            'familia'   => $familia,
            'productos' => $this->agregarPrecio(DB::select('SELECT * FROM inventarios.fn_familia_productos(?)', [$famId])),
        ];
    }

    /** {registros, total}: los que se pueden agregar (misma categoría y marca, sin otra familia), con su precio */
    public function candidatos(int $famId, string $busqueda, int $pagina, int $tamanio): array
    {
        $resultado = $this->pagina(DB::select('SELECT * FROM inventarios.fn_familia_candidatos(?, ?, ?, ?)', [$famId, $busqueda, $pagina, $tamanio]));
        $resultado['registros'] = $this->agregarPrecio($resultado['registros']);

        return $resultado;
    }

    /** $datos: {pro_id, fam_nombre, car_id_1, car_id_2?, usu_id}. El producto da la categoría y la marca y queda como principal. */
    public function crear(array $datos): int
    {
        $famId = DB::selectOne('SELECT inventarios.fn_familia_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        ProductoTiendaService::limpiarCacheTienda();

        return $famId;
    }

    /** $datos: solo lo que cambia (fam_nombre, car_id_1, car_id_2: null la quita, pro_id_principal) + usu_id */
    public function modificar(int $famId, array $datos): void
    {
        DB::statement('SELECT inventarios.fn_familia_modificar(?, ?::jsonb)', [$famId, $this->json($datos)]);
        ProductoTiendaService::limpiarCacheTienda();
    }

    public function agregarProducto(int $famId, int $proId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_familia_producto_agregar(?, ?, ?)', [$famId, $proId, $usuId]);
        ProductoTiendaService::limpiarCacheTienda();
    }

    /** Si era el principal, pasa al siguiente; el último no se quita (FAMILIA_SIN_PRODUCTOS) */
    public function quitarProducto(int $famId, int $proId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_familia_producto_quitar(?, ?, ?)', [$famId, $proId, $usuId]);
        ProductoTiendaService::limpiarCacheTienda();
    }

    /** A la papelera: sus SKU quedan libres */
    public function eliminar(int $famId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_familia_eliminar(?, ?)', [$famId, $usuId]);
        ProductoTiendaService::limpiarCacheTienda();
    }

    /** Vuelve de la papelera. Devuelve cuántos de sus SKU se quedaron en otra familia (no vuelven). */
    public function restaurar(int $famId, int $usuId): int
    {
        $quedaron = (int) DB::selectOne('SELECT inventarios.fn_familia_restaurar(?, ?) AS quedaron', [$famId, $usuId])->quedaron;
        ProductoTiendaService::limpiarCacheTienda();

        return $quedaron;
    }

    /** {registros, total}: [{fam_id, fam_nombre, categoria, marca, productos, fam_eliminado_en, fam_eliminado_usu_id, eliminado_por}] */
    public function papelera(string $busqueda, int $pagina, int $tamanio): array
    {
        $resultado = $this->pagina(DB::select('SELECT * FROM inventarios.fn_familia_papelera(?, ?, ?)', [$busqueda, $pagina, $tamanio]));
        $resultado['registros'] = PapeleraPaginaWeb::agregarEliminadoPor($resultado['registros'], 'fam_eliminado_usu_id');

        return $resultado;
    }

    /** Borra para siempre las familias de la papelera. Devuelve cuántas. */
    public function vaciar(int $usuId): int
    {
        $cantidad = (int) DB::selectOne('SELECT inventarios.fn_familia_vaciar(?) AS cantidad', [$usuId])->cantidad;
        ProductoTiendaService::limpiarCacheTienda();

        return $cantidad;
    }

    /** Para el editor de un producto: {fam_id, fam_nombre, es_principal, aviso}, o null si no está en ninguna */
    public function deProducto(int $proId): ?object
    {
        return DB::selectOne('SELECT * FROM inventarios.fn_familia_de_producto(?)', [$proId]);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Filas paginadas de la base (cada una con su total) → {registros, total} */
    private function pagina(array $filas): array
    {
        return ['registros' => $filas, 'total' => (int) ($filas[0]->total ?? 0)];
    }

    /** A cada SKU le agrega su precio de la tienda (null si no está publicado) */
    private function agregarPrecio(array $filas): array
    {
        $publicados = $this->productos->publicados()['productos'];

        foreach ($filas as $fila) {
            $fila->precio = $publicados[(int) $fila->pro_id]['precio'] ?? null;
        }

        return $filas;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
