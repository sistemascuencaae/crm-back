<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Productos de la tienda: ficha técnica (plantilla de características por categoría + datos sueltos)
 * e imágenes por sección (detalle, carátula, promoción). Llama a las funciones inventarios.fn_*.
 * Del ERP solo se leen código, nombre, categoría, marca, descripción, precio de venta y stock: nunca costos ni márgenes.
 * Qué productos están publicados (con su precio y stock) sale de una consulta lenta (~12 s): se guarda 10 minutos en caché.
 */
class ProductoTiendaService
{
    private const CACHE_PUBLICADOS   = 'pagina_web.productos.publicados';
    private const MINUTOS_PUBLICADOS = 10;
    private const TAMANIOS           = [10, 20, 30, 50, 100];   // los mismos que acepta fn_producto_web_listar

    // Códigos propios (los CAMPO_*, CAMPO_FECHA, FECHAS_RANGO y ORDEN_* comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'CATEGORIA_NO_EXISTE'        => 'La categoría no existe o está inactiva en el sistema.',
        'PRODUCTO_NO_EXISTE'         => 'El producto no existe o está inactivo en el sistema.',
        'CARACTERISTICA_NO_EXISTE'   => 'La característica no existe.',
        'CARACTERISTICA_REPETIDA'    => 'Ya hay una característica activa con ese nombre en esta categoría o en una de arriba o de abajo.',
        'CARACTERISTICAS_MAXIMO'     => 'Una categoría puede tener como máximo 30 características.',
        'CARACTERISTICA_TIPO_EN_USO' => 'Ya hay productos con este dato lleno: el tipo no se puede cambiar.',
        'FICHA_INVALIDA'             => 'Los datos de la ficha no tienen un formato válido.',
        'FICHA_DESACTUALIZADA'       => 'La plantilla de esta categoría cambió. Recarga la página.',
        'CAMPO_NUMERO'               => 'Escribe solo un número (por ejemplo 150 o 150.5).',
        'SUELTA_REPETIDA'            => 'Ese nombre ya está en la ficha.',
        'SUELTAS_MAXIMO'             => 'Se pueden agregar como máximo 20 datos sueltos.',
        'IMAGEN_NO_EXISTE'           => 'La imagen no existe.',
        'IMAGENES_MAXIMO'            => 'Cada sección puede tener como máximo 5 imágenes.',
        'SECCION_INVALIDA'           => 'La sección no es válida.',
        'CAMPO_IMAGEN'               => 'La dirección de la imagen no es válida.',
        'IMAGEN_WEBP'                => 'La imagen debe ser WEBP.',
    ];

    // -----------------------------------------------------------------
    // Publicados (con caché)
    // -----------------------------------------------------------------

    /**
     * Los productos publicados en la tienda con su precio de venta y stock:
     *   ['actualizado' => '2026-10-28T10:15:00-05:00', 'productos' => [pro_id => ['precio' => float, 'stock' => float]]]
     * No hay botón ni tarea programada: la caché se renueva sola al vencer.
     */
    public function publicados(): array
    {
        $guardados = Cache::get(self::CACHE_PUBLICADOS);
        if ($guardados !== null) {
            return $guardados;
        }

        $leer = fn () => Cache::remember(self::CACHE_PUBLICADOS, now()->addMinutes(self::MINUTOS_PUBLICADOS), fn () => $this->leerPublicados());

        if (!(Cache::getStore() instanceof LockProvider)) {
            return $leer();
        }

        // Si otra petición ya la está llenando, se espera a que termine y se usa lo que dejó
        return Cache::lock(self::CACHE_PUBLICADOS . '.llenando', 60)->block(45, $leer);
    }

    // -----------------------------------------------------------------
    // Productos
    // -----------------------------------------------------------------

    /** Árbol de categorías activas con cuántos productos tiene cada rama: [{tpr_id, tpr_reporta, tpr_nivel, tpr_nombre, total_productos}] */
    public function arbol(bool $soloPublicados): array
    {
        $publicados = $soloPublicados ? $this->arreglo(array_keys($this->publicados()['productos'])) : null;

        return DB::select('SELECT * FROM inventarios.fn_producto_web_arbol(?::integer[])', [$publicados]);
    }

    /**
     * Productos activos paginados (con tpr_id, toda la rama). Con $incompletos, solo a los que les falta contenido;
     * con $ofertaSemana / $destacado, solo los marcados así para el inicio.
     * Cada fila lleva cuántas imágenes tiene por sección, cuánto de su ficha está lleno, imagen (primera carátula o
     * del detalle, o null), completo, pro_nombre_web (o null), pro_oferta_semana, pro_destacado (bool) y, de la caché,
     * publicado / precio / stock. La búsqueda también mira el nombre web.
     *   {registros, total, pagina, tamanio, publicados_actualizado}
     */
    public function listar(int $pagina, int $tamanio, string $busqueda, ?int $tprId, bool $soloPublicados,
                           bool $incompletos = false, bool $ofertaSemana = false, bool $destacado = false): array
    {
        $publicados = $this->publicados();

        $registros = DB::select('SELECT * FROM inventarios.fn_producto_web_listar(?, ?, ?, ?, ?::integer[], ?::boolean, ?::boolean, ?::boolean)', [
            $pagina,
            $tamanio,
            $busqueda,
            $tprId,
            $soloPublicados ? $this->arreglo(array_keys($publicados['productos'])) : null,
            $incompletos ? 'true' : 'false',
            $ofertaSemana ? 'true' : 'false',
            $destacado ? 'true' : 'false',
        ]);

        foreach ($registros as $fila) {
            $this->agregarPublicado($fila, $publicados['productos']);
            $this->marcasInicio($fila);
        }

        return [
            'registros'              => $registros,
            'total'                  => (int) ($registros[0]->total_registros ?? 0),
            'pagina'                 => max($pagina, 1),
            'tamanio'                => in_array($tamanio, self::TAMANIOS, true) ? $tamanio : 20,
            'publicados_actualizado' => $publicados['actualizado'],
        ];
    }

    /**
     * Todo lo que necesita el editor de un producto, o null si no existe o está inactivo:
     *   {producto: {…, pro_nombre_web, pro_oferta_semana, pro_destacado, publicado, precio, stock}, ficha: [...], imagenes: [...], papelera_imagenes: [...]}
     */
    public function buscar(int $proId): ?array
    {
        $producto = DB::selectOne('SELECT * FROM inventarios.fn_producto_web_buscar(?)', [$proId]);

        if ($producto === null) {
            return null;
        }

        $this->agregarPublicado($producto, $this->publicados()['productos']);
        $this->marcasInicio($producto);

        return [
            'producto'          => $producto,
            'ficha'             => DB::select('SELECT * FROM inventarios.fn_producto_ficha(?)', [$proId]),
            'imagenes'          => DB::select('SELECT * FROM inventarios.fn_producto_imagen_listar(?)', [$proId]),
            'papelera_imagenes' => PapeleraPaginaWeb::agregarEliminadoPor(
                DB::select('SELECT * FROM inventarios.fn_producto_imagen_papelera(?)', [$proId]),
                'pim_eliminado_usu_id'
            ),
        ];
    }

    /** $datos: {valores: {car_id: valor}, sueltas: [{nombre, valor}], usu_id}. Un valor vacío borra ese dato. */
    public function guardarFicha(int $proId, array $datos): void
    {
        // {} vacío llega a PHP como [] y se enviaría como arreglo: debe ir como objeto
        if (isset($datos['valores']) && is_array($datos['valores'])) {
            $datos['valores'] = (object) $datos['valores'];
        }

        DB::statement('SELECT inventarios.fn_producto_ficha_guardar(?, ?::jsonb)', [$proId, $this->json($datos)]);
    }

    /** $datos: solo lo que cambia (pro_nombre_web, pro_oferta_semana, pro_destacado) + usu_id. pro_nombre_web vacío = vuelve al nombre del ERP. */
    public function modificar(int $proId, array $datos): void
    {
        DB::statement('SELECT inventarios.fn_producto_web_modificar(?, ?::jsonb)', [$proId, $this->json($datos)]);
        self::limpiarCacheTienda();
    }

    // -----------------------------------------------------------------
    // Imágenes (las fotos salen en las tarjetas y carruseles del inicio y de las páginas de marca)
    // -----------------------------------------------------------------

    /** $datos: {pro_id, pim_seccion, pim_imagen, pim_texto, pim_visible, pim_desde, pim_hasta, usu_id}. Devuelve su id. */
    public function crearImagen(array $datos): int
    {
        $pimId = DB::selectOne('SELECT inventarios.fn_producto_imagen_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        self::limpiarCacheTienda();

        return $pimId;
    }

    /** $datos: solo lo que cambia + usu_id (el producto y la sección no se cambian) */
    public function modificarImagen(int $pimId, array $datos): void
    {
        DB::statement('SELECT inventarios.fn_producto_imagen_modificar(?, ?::jsonb)', [$pimId, $this->json($datos)]);
        self::limpiarCacheTienda();
    }

    /** La manda a la papelera */
    public function eliminarImagen(int $pimId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_producto_imagen_eliminar(?, ?)', [$pimId, $usuId]);
        self::limpiarCacheTienda();
    }

    /** La saca de la papelera, al final de su sección */
    public function restaurarImagen(int $pimId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_producto_imagen_restaurar(?, ?)', [$pimId, $usuId]);
        self::limpiarCacheTienda();
    }

    /** $datos: {pro_id, pim_seccion, ids: [pim_id…] (todas las de la sección, en el nuevo orden), usu_id} */
    public function ordenarImagenes(array $datos): void
    {
        DB::statement('SELECT inventarios.fn_producto_imagen_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCacheTienda();
    }

    /** Borra para siempre las imágenes de la papelera del producto, con sus archivos. Devuelve cuántas borró. */
    public function vaciarImagenes(int $proId, int $usuId): int
    {
        $imagenes = array_column(DB::select('SELECT * FROM inventarios.fn_producto_imagen_vaciar(?, ?)', [$proId, $usuId]), 'imagen');
        ArchivosPaginaWeb::borrar($imagenes);
        self::limpiarCacheTienda();

        return count($imagenes);
    }

    // -----------------------------------------------------------------
    // Tienda (lo usan InicioSeccionService, BloqueProductoService y PaginaMarcaService, que ya tienen su caché)
    // -----------------------------------------------------------------

    /**
     * Tarjetas de productos publicados, con la misma forma en todo el sitio (nombre = el de la tienda):
     *   [{pro_id, codigo, nombre, marca, tpr_id, categoria, imagenes: [url…], destacado, precio, stock}]
     * imagenes: todas las carátulas en orden; sin carátulas, la primera del detalle; sin fotos, [].
     * Filtros opcionales: solo ofertas de la semana, solo destacados, una categoría (con su rama) o unas marcas
     * ($marcas: [mar_id…]; null = cualquier marca). $cantidad null = todas.
     */
    public function tarjetas(bool $ofertaSemana, bool $destacado, ?int $tprId, ?array $marcas, ?int $cantidad): array
    {
        $publicados = $this->publicados()['productos'];

        $filas = DB::select('SELECT * FROM inventarios.fn_producto_tarjeta_listar_publicas(?::integer[], ?::boolean, ?::boolean, ?, ?::integer[], ?)', [
            $this->arreglo(array_keys($publicados)),
            $ofertaSemana ? 'true' : 'false',
            $destacado ? 'true' : 'false',
            $tprId,
            $marcas === null ? null : $this->arreglo($marcas),
            $cantidad,
        ]);

        return array_map(fn ($f) => [
            'pro_id'    => (int) $f->pro_id,
            'codigo'    => $f->pro_codigo,
            'nombre'    => $f->pro_nombre,
            'marca'     => $f->marca,
            'tpr_id'    => (int) $f->tpr_id,
            'categoria' => $f->categoria,
            'imagenes'  => json_decode($f->imagenes ?? '[]', true) ?? [],
            'destacado' => (bool) $f->destacado,
            'precio'    => $publicados[(int) $f->pro_id]['precio'] ?? null,
            'stock'     => $publicados[(int) $f->pro_id]['stock'] ?? null,
        ], $filas);
    }

    /**
     * Tarjetas de promoción de los productos publicados, una por producto:
     *   [{pro_id, marca, nombre, imagen, tpr_id, precio}]
     * Filtros opcionales (null = no filtra): una categoría (con su rama) o unas marcas ([mar_id…]).
     */
    public function promociones(?int $tprId, ?array $marcas): array
    {
        $publicados = $this->publicados()['productos'];

        $filas = DB::select('SELECT * FROM inventarios.fn_producto_promocion_listar_publicas(?::integer[], ?, ?::integer[])', [
            $this->arreglo(array_keys($publicados)),
            $tprId,
            $marcas === null ? null : $this->arreglo($marcas),
        ]);

        return array_map(fn ($f) => [
            'pro_id' => (int) $f->pro_id,
            'marca'  => $f->marca,
            'nombre' => $f->nombre,
            'imagen' => $f->imagen,
            'tpr_id' => (int) $f->tpr_id,
            'precio' => $publicados[(int) $f->pro_id]['precio'] ?? null,
        ], $filas);
    }

    /** El inicio y las páginas de marca muestran productos: tras marcar uno o cambiar sus fotos, deben verlo. */
    public static function limpiarCacheTienda(): void
    {
        InicioSeccionService::limpiarCache();
        PaginaMarcaService::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Características (plantilla de la ficha por categoría)
    // -----------------------------------------------------------------

    /** {plantilla: las de la categoría y las heredadas de arriba, papelera: las eliminadas de esta categoría} */
    public function caracteristicas(int $tprId): array
    {
        return [
            'plantilla' => DB::select('SELECT * FROM inventarios.fn_caracteristica_listar(?)', [$tprId]),
            'papelera'  => PapeleraPaginaWeb::agregarEliminadoPor(
                DB::select('SELECT * FROM inventarios.fn_caracteristica_papelera(?)', [$tprId]),
                'car_eliminado_usu_id'
            ),
        ];
    }

    /** $datos: {tpr_id, car_nombre, car_tipo (texto | numero), car_unidad, usu_id}. Devuelve su id. */
    public function crearCaracteristica(array $datos): int
    {
        return DB::selectOne('SELECT inventarios.fn_caracteristica_crear(?::jsonb) AS id', [$this->json($datos)])->id;
    }

    /** $datos: solo lo que cambia + usu_id (la categoría no se cambia) */
    public function modificarCaracteristica(int $carId, array $datos): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_modificar(?, ?::jsonb)', [$carId, $this->json($datos)]);
    }

    /** La manda a la papelera (los valores de los productos se conservan) */
    public function eliminarCaracteristica(int $carId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_eliminar(?, ?)', [$carId, $usuId]);
    }

    public function restaurarCaracteristica(int $carId, int $usuId): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_restaurar(?, ?)', [$carId, $usuId]);
    }

    /** $datos: {tpr_id, ids: [car_id…] (todas las de la categoría, en el nuevo orden), usu_id} */
    public function ordenarCaracteristicas(array $datos): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_ordenar(?::jsonb)', [$this->json($datos)]);
    }

    /** Borra para siempre las características de la papelera de la categoría (y sus valores en los productos). Devuelve cuántas. */
    public function vaciarCaracteristicas(int $tprId, int $usuId): int
    {
        return (int) DB::selectOne('SELECT inventarios.fn_caracteristica_vaciar(?, ?) AS cantidad', [$tprId, $usuId])->cantidad;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function leerPublicados(): array
    {
        $productos = [];
        foreach (DB::select('SELECT * FROM inventarios.fn_producto_publicado_listar()') as $fila) {
            $productos[(int) $fila->pro_id] = ['precio' => (float) $fila->precio, 'stock' => (float) $fila->stock];
        }

        return ['actualizado' => now()->format('Y-m-d\TH:i:sP'), 'productos' => $productos];
    }

    /** publicado (bool), precio y stock (null si no está publicado) */
    private function agregarPublicado(object $fila, array $productos): void
    {
        $publicado       = $productos[(int) $fila->pro_id] ?? null;
        $fila->publicado = $publicado !== null;
        $fila->precio    = $publicado['precio'] ?? null;
        $fila->stock     = $publicado['stock'] ?? null;
    }

    /** pro_oferta_semana y pro_destacado siempre como bool */
    private function marcasInicio(object $fila): void
    {
        $fila->pro_oferta_semana = (bool) $fila->pro_oferta_semana;
        $fila->pro_destacado     = (bool) $fila->pro_destacado;
    }

    /** Lista de números como arreglo de Postgres: [1, 2, 3] → "{1,2,3}" */
    private function arreglo(array $ids): string
    {
        return '{' . implode(',', array_map('intval', $ids)) . '}';
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
