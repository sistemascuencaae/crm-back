<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Productos de la tienda: ficha técnica (plantilla de características por categoría + datos sueltos)
 * e imágenes por sección (detalle, carátula, promoción). Llama a las funciones inventarios.fn_*.
 * Del ERP solo se leen código, nombre, categoría, marca, descripción, precio de venta y stock: nunca costos ni márgenes.
 * Qué productos están publicados (con su precio, precio anterior y stock) sale de una consulta lenta (~12 s): se guarda
 * en caché, que renueva el programador cada 5 minutos. La búsqueda pública del shop (buscarTienda) usa esa caché.
 */
class ProductoTiendaService
{
    private const CACHE_PUBLICADOS   = 'pagina_web.productos.publicados';
    private const MINUTOS_PUBLICADOS = 10;
    private const MINUTOS_RENOVADOS  = 15;    // más que los 5 minutos del programador: mientras corra, nunca vence
    private const TAMANIOS          = [10, 20, 30, 50, 100];   // los mismos que acepta fn_producto_web_listar

    // Lo único que se pasa a la búsqueda del shop: los mismos nombres que la dirección de /shop
    public const FILTROS_SHOP = ['search', 'categoria', 'marca', 'price_min', 'price_max', 'in_stock', 'motos', 'sort', 'page', 'per_page'];

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
     * Los productos publicados en la tienda con su precio de venta, su precio anterior (con tarjeta, va tachado) y stock:
     *   ['actualizado' => '2026-10-28T10:15:00-05:00',
     *    'productos' => [pro_id => ['precio' => float, 'precio_anterior' => float | null, 'stock' => float]]]
     * La renueva el programador cada 5 minutos (renovarPublicados), así ninguna visita paga la consulta (~6 s). Si el
     * programador no corre, la visita que la encuentra vacía la calcula y la guarda 10 minutos.
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

    /** Calcula los publicados y reemplaza la caché por 15 minutos (comando pagina-web:renovar-publicados, cada 5 minutos) */
    public function renovarPublicados(): void
    {
        Cache::put(self::CACHE_PUBLICADOS, $this->leerPublicados(), now()->addMinutes(self::MINUTOS_RENOVADOS));
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
     * publicado / precio / precio_anterior / stock. La búsqueda también mira el nombre web.
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
     *   {producto: {…, pro_nombre_web, pro_oferta_semana, pro_destacado, publicado, precio, precio_anterior, stock}, ficha: [...], imagenes: [...],
     *    papelera_imagenes: {registros, total} (la 1.ª página; las demás, con papeleraImagenes)}
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
            'ficha'             => $this->ficha($proId),
            'imagenes'          => DB::select('SELECT * FROM inventarios.fn_producto_imagen_listar(?)', [$proId]),
            'papelera_imagenes' => $this->papeleraImagenes($proId, '', 1, 20),
        ];
    }

    /** {registros, total}: las imágenes eliminadas del producto, con eliminado_por */
    public function papeleraImagenes(int $proId, string $busqueda, int $pagina, int $tamanio): array
    {
        return PapeleraPaginaWeb::pagina(
            DB::select('SELECT * FROM inventarios.fn_producto_imagen_papelera(?, ?, ?, ?)', [$proId, $busqueda, $pagina, $tamanio]),
            'pim_eliminado_usu_id'
        );
    }

    /** La plantilla de su categoría con lo que tenga lleno (origen = plantilla) y sus datos sueltos (origen = suelta) */
    public function ficha(int $proId): array
    {
        return DB::select('SELECT * FROM inventarios.fn_producto_ficha(?)', [$proId]);
    }

    /** $datos: {valores: {car_id: valor}, sueltas: [{nombre, valor}]}. Un valor vacío borra ese dato. */
    public function guardarFicha(int $proId, array $datos): void
    {
        // {} vacío llega a PHP como [] y se enviaría como arreglo: debe ir como objeto
        if (isset($datos['valores']) && is_array($datos['valores'])) {
            $datos['valores'] = (object) $datos['valores'];
        }

        DB::statement('SELECT inventarios.fn_producto_ficha_guardar(?::jsonb)', [$this->jsonAuditado(['pro_id' => $proId] + $datos)]);
    }

    /**
     * $datos: solo lo que cambia (pro_nombre_web, pro_observacion, pro_oferta_semana, pro_destacado) + usu_id.
     * pro_nombre_web vacío = vuelve al nombre del ERP; pro_observacion es la descripción (la del ERP; vacía = sin descripción).
     */
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
     *   [{pro_id, codigo, nombre, marca, tpr_id, categoria, imagenes: [url…], destacado, precio, precio_anterior, stock,
     *     familia, variantes}]
     * imagenes: todas las carátulas en orden; sin carátulas, la primera del detalle; sin fotos, [].
     * Una familia sale en una sola tarjeta: familia = su nombre, variantes = cuántas están publicadas, precio = el menor
     * de ellas ("Desde") y precio_anterior = null. Un producto suelto: familia y variantes null.
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

        return array_map(function ($f) use ($publicados) {
            $datos = $publicados[(int) $f->pro_id] ?? [];

            if ($f->familia !== null) {
                // variantes llega como arreglo de Postgres con los pro_id publicados de la familia: "{12,15,20}"
                $variantes = array_map('intval', array_filter(explode(',', trim((string) $f->variantes, '{}'))));
                $precios   = array_filter(array_map(fn ($id) => $publicados[$id]['precio'] ?? null, $variantes), fn ($p) => $p !== null);
                $datos     = ['precio' => $precios ? min($precios) : null, 'precio_anterior' => null, 'stock' => $datos['stock'] ?? null,
                              'variantes' => count($variantes)];
            }

            return $this->tarjeta($f, $datos);
        }, $filas);
    }

    /**
     * Búsqueda del shop (GET public/pagina-web/productos). $filtros: solo las claves de FILTROS_SHOP que llegaron;
     * lo que no es válido, la base lo ignora. Sin caché de la respuesta: solo la de publicados.
     * La cuadrícula de la página de marca la usa con {marcas, sort: 'nombre', destacados?, page, per_page} y sin opciones
     * (PaginaMarcaService::productosPublicos).
     * Una familia sale en una sola tarjeta (familia, variantes y precio "Desde" ya vienen de la base).
     *   {productos: [tarjetas], total, pagina, por_pagina, ultima_pagina,
     *    opciones (solo con $conOpciones): {categorias: [{tpr_id, nombre, cantidad}], marcas: [{mar_id, nombre, cantidad}], precio: {min, max} | null}}
     */
    public function buscarTienda(array $filtros, bool $conOpciones = true): array
    {
        $publicados = $this->publicadosJson();
        $json       = $this->json($filtros);

        $filas = DB::select('SELECT * FROM inventarios.fn_producto_tienda_buscar(?::jsonb, ?::jsonb)', [$publicados, $json]);

        $total     = (int) ($filas[0]->total ?? 0);
        // La misma regla que la base: de 1 a 48; si no, 12
        $porPagina = preg_match('/^\d{1,2}$/', (string) ($filtros['per_page'] ?? '')) && $filtros['per_page'] >= 1 && $filtros['per_page'] <= 48
            ? (int) $filtros['per_page'] : 12;

        $resultado = [
            'productos'     => array_map(fn ($f) => $this->tarjetaTienda($f), $filas),
            'total'         => $total,
            'pagina'        => (int) ($filas[0]->pagina ?? 1),
            'por_pagina'    => $porPagina,
            'ultima_pagina' => max(1, (int) ceil($total / $porPagina)),
        ];

        if ($conOpciones) {
            $resultado['opciones'] = $this->opcionesTienda($publicados, $json);
        }

        return $resultado;
    }

    /** Las opciones del panel de filtros del shop para lo que se buscó (consulta aparte, ~370 ms) */
    private function opcionesTienda(string $publicados, string $json): array
    {
        $opciones = ['categorias' => [], 'marcas' => [], 'precio' => null];

        foreach (DB::select('SELECT * FROM inventarios.fn_producto_tienda_opciones(?::jsonb, ?::jsonb)', [$publicados, $json]) as $o) {
            if ($o->tipo === 'categoria') {
                $opciones['categorias'][] = ['tpr_id' => (int) $o->id, 'nombre' => $o->nombre, 'cantidad' => (int) $o->cantidad];
            } elseif ($o->tipo === 'marca') {
                $opciones['marcas'][] = ['mar_id' => (int) $o->id, 'nombre' => $o->nombre, 'cantidad' => (int) $o->cantidad];
            } elseif ($o->tipo === 'precio' && $o->minimo !== null) {
                $opciones['precio'] = ['min' => (float) $o->minimo, 'max' => (float) $o->maximo];
            }
        }

        return $opciones;
    }

    /**
     * Detalle de un producto para la tienda, o null si no está publicado o su categoría no se ve en la tienda:
     *   {producto: {pro_id, codigo, nombre, descripcion, tpr_id, categoria, destacado, imagenes, ficha: [{nombre, valor, unidad}],
     *               precio, precio_anterior, marca: {mar_id, nombre, logo, enlace} | null},
     *    familia: null | {nombre, caracteristicas: [{nombre, unidad}], variantes: [{pro_id, nombre, valores, posiciones,
     *                     precio, precio_anterior, actual}]},
     *    relacionados: [hasta 8 tarjetas de su categoría, sin él ni las otras variantes de su familia]}
     * Sin caché: el precio sale de la caché de publicados, como en el shop. Las franjas las agrega el controlador.
     */
    public function detalle(int $proId): ?array
    {
        $publicados = $this->publicados()['productos'];
        $precios    = $publicados[$proId] ?? null;
        $p          = $precios === null ? null : DB::selectOne('SELECT * FROM inventarios.fn_producto_detalle_publico(?)', [$proId]);

        if ($p === null) {
            return null;
        }

        $familia = $this->familiaDelDetalle($proId, $publicados);
        $fuera   = $familia === null ? [$proId] : array_column($familia['variantes'], 'pro_id');

        $relacionados = array_filter(
            DB::select('SELECT * FROM inventarios.fn_producto_tienda_buscar(?::jsonb, ?::jsonb)', [
                $this->publicadosJson(),
                $this->json(['categoria' => (int) $p->tpr_id, 'per_page' => 12]),
            ]),
            fn ($f) => !in_array((int) $f->pro_id, $fuera, true)
        );

        return [
            'producto'     => [
                'pro_id'          => (int) $p->pro_id,
                'codigo'          => $p->codigo,
                'nombre'          => $p->nombre,
                'descripcion'     => $p->descripcion,
                'tpr_id'          => (int) $p->tpr_id,
                'categoria'       => $p->categoria,
                'destacado'       => (bool) $p->destacado,
                'imagenes'        => json_decode($p->imagenes ?? '[]', true) ?? [],
                'ficha'           => json_decode($p->ficha ?? '[]', true) ?? [],
                'precio'          => $precios['precio'],
                'precio_anterior' => $precios['precio_anterior'],
                'marca'           => $p->mar_id === null ? null : [
                    'mar_id' => (int) $p->mar_id,
                    'nombre' => $p->marca,
                    'logo'   => $p->marca_logo,
                    'enlace' => $p->marca_enlace,
                ],
            ],
            'familia'      => $familia,
            'relacionados' => array_map(fn ($f) => $this->tarjetaTienda($f), array_slice(array_values($relacionados), 0, 8)),
        ];
    }

    /**
     * La familia del producto para el detalle (los botones de las variantes), o null si no es de ninguna o le quedan menos
     * de 2 variantes válidas y publicadas. Cada variante con su precio de la caché de publicados.
     */
    private function familiaDelDetalle(int $proId, array $publicados): ?array
    {
        $filas = DB::select('SELECT * FROM inventarios.fn_familia_detalle_publico(?, ?::integer[])', [$proId, $this->arreglo(array_keys($publicados))]);

        if (!$filas) {
            return null;
        }

        $f               = $filas[0];
        $caracteristicas = [['nombre' => $f->caracteristica_1, 'unidad' => $f->unidad_1]];
        if ($f->caracteristica_2 !== null) {
            $caracteristicas[] = ['nombre' => $f->caracteristica_2, 'unidad' => $f->unidad_2];
        }
        $dos = count($caracteristicas) === 2;

        return [
            'nombre'          => $f->fam_nombre,
            'caracteristicas' => $caracteristicas,
            'variantes'       => array_map(fn ($v) => [
                'pro_id'          => (int) $v->pro_id,
                'nombre'          => $v->nombre,
                'valores'         => $dos ? [$v->valor_1, $v->valor_2] : [$v->valor_1],
                'posiciones'      => $dos ? [(int) $v->posicion_1, (int) $v->posicion_2] : [(int) $v->posicion_1],
                'precio'          => $publicados[(int) $v->pro_id]['precio'] ?? null,
                'precio_anterior' => $publicados[(int) $v->pro_id]['precio_anterior'] ?? null,
                'actual'          => (bool) $v->actual,
            ], $filas),
        ];
    }

    /**
     * Las categorías visibles en la tienda que tienen productos publicados en su rama: [tpr_id…].
     * La tienda solo muestra esas (menú, destacadas y mosaico): nadie entra a una categoría vacía.
     */
    public function categoriasConProductos(): array
    {
        $filas = DB::select("SELECT id FROM inventarios.fn_producto_tienda_opciones(?::jsonb, '{}'::jsonb) WHERE tipo = 'categoria'", [$this->publicadosJson()]);

        return array_map(fn ($f) => (int) $f->id, $filas);
    }

    /**
     * Tarjetas del carrusel de un bloque destacado: los productos destacados publicados, uno por producto:
     *   [{pro_id, marca, nombre, imagen, tpr_id, precio, precio_anterior}]
     * imagen: la de Promoción o, si no tiene, la carátula o una del detalle; null sin fotos.
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
            'precio'          => $publicados[(int) $f->pro_id]['precio'] ?? null,
            'precio_anterior' => $publicados[(int) $f->pro_id]['precio_anterior'] ?? null,
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

    /**
     * {plantilla: las de la categoría,
     *  papelera: {registros, total} las eliminadas de esta categoría (la 1.ª página; las demás, con papeleraCaracteristicas)}
     */
    public function caracteristicas(int $tprId): array
    {
        $plantilla = DB::select('SELECT * FROM inventarios.fn_caracteristica_listar_paginacion(1, 100, ?, ?)', ['', $tprId]);

        foreach ($plantilla as $fila) {
            unset($fila->total_registros);
        }

        return [
            'plantilla' => $plantilla,
            'papelera'  => $this->papeleraCaracteristicas($tprId, '', 1, 20),
        ];
    }

    /** {registros, total}: las características eliminadas de la categoría, con eliminado_por */
    public function papeleraCaracteristicas(int $tprId, string $busqueda, int $pagina, int $tamanio): array
    {
        $filas = DB::select('SELECT * FROM inventarios.fn_caracteristica_papelera_paginacion(?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $tprId]);

        foreach ($filas as $fila) {
            $fila->total = $fila->total_registros;
            unset($fila->total_registros);
        }

        return PapeleraPaginaWeb::pagina($filas, 'car_eliminado_usu_id');
    }

    /** $datos: {tpr_id, car_nombre, car_tipo (texto | numero), car_unidad}. Devuelve su id. */
    public function crearCaracteristica(array $datos): int
    {
        return DB::selectOne('SELECT inventarios.fn_caracteristica_registrar(?::jsonb) AS id', [$this->jsonAuditado($datos)])->id;
    }

    /** $datos: solo lo que cambia (la categoría no se cambia) */
    public function modificarCaracteristica(int $carId, array $datos): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_modificar(?::jsonb)', [$this->jsonAuditado(['car_id' => $carId] + $datos)]);
    }

    /** La manda a la papelera (los valores de los productos se conservan) */
    public function eliminarCaracteristica(int $carId): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_eliminar(?::jsonb)', [$this->jsonAuditado(['car_id' => $carId])]);
    }

    public function restaurarCaracteristica(int $carId): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_restaurar(?::jsonb)', [$this->jsonAuditado(['car_id' => $carId])]);
    }

    /** $datos: {tpr_id, ids: [car_id…] (todas las de la categoría, en el nuevo orden)} */
    public function ordenarCaracteristicas(array $datos): void
    {
        DB::statement('SELECT inventarios.fn_caracteristica_ordenar(?::jsonb)', [$this->jsonAuditado($datos)]);
    }

    /** Borra para siempre las características de la papelera de la categoría (y sus valores en los productos). Devuelve cuántas. */
    public function vaciarCaracteristicas(int $tprId): int
    {
        return (int) DB::selectOne('SELECT inventarios.fn_caracteristica_vaciar(?::jsonb) AS cantidad', [$this->jsonAuditado(['tpr_id' => $tprId])])->cantidad;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function leerPublicados(): array
    {
        $productos = [];
        foreach (DB::select('SELECT * FROM inventarios.fn_producto_publicado_listar()') as $fila) {
            $productos[(int) $fila->pro_id] = [
                'precio'          => (float) $fila->precio,
                'precio_anterior' => $fila->precio_anterior === null ? null : (float) $fila->precio_anterior,
                'stock'           => (float) $fila->stock,
            ];
        }

        return ['actualizado' => now()->format('Y-m-d\TH:i:sP'), 'productos' => $productos];
    }

    /**
     * La tarjeta de producto de todo el sitio. $datos: {precio, precio_anterior, stock, variantes} (vacío = no está
     * publicado). familia: el nombre de la familia si la tarjeta es de una (con variantes = cuántas); si no, null.
     */
    private function tarjeta(object $f, array $datos): array
    {
        return [
            'pro_id'          => (int) $f->pro_id,
            'codigo'          => $f->pro_codigo,
            'nombre'          => $f->pro_nombre,
            'marca'           => $f->marca,
            'tpr_id'          => (int) $f->tpr_id,
            'categoria'       => $f->categoria,
            'imagenes'        => json_decode($f->imagenes ?? '[]', true) ?? [],
            'destacado'       => (bool) $f->destacado,
            'precio'          => $datos['precio'] ?? null,
            'precio_anterior' => $datos['precio_anterior'] ?? null,
            'stock'           => $datos['stock'] ?? null,
            'familia'         => $f->familia ?? null,
            'variantes'       => $datos['variantes'] ?? null,
        ];
    }

    /** Tarjeta de una fila de fn_producto_tienda_buscar, que ya trae precio ("Desde" en una familia), stock y variantes */
    private function tarjetaTienda(object $f): array
    {
        return $this->tarjeta($f, [
            'precio'          => (float) $f->precio,
            'precio_anterior' => $f->precio_anterior === null ? null : (float) $f->precio_anterior,
            'stock'           => (float) $f->stock,
            'variantes'       => $f->variantes === null ? null : (int) $f->variantes,
        ]);
    }

    /** publicado (bool), precio, precio_anterior y stock (null si no está publicado) */
    private function agregarPublicado(object $fila, array $productos): void
    {
        $publicado             = $productos[(int) $fila->pro_id] ?? null;
        $fila->publicado       = $publicado !== null;
        $fila->precio          = $publicado['precio'] ?? null;
        $fila->precio_anterior = $publicado['precio_anterior'] ?? null;
        $fila->stock           = $publicado['stock'] ?? null;
    }

    /** pro_oferta_semana y pro_destacado siempre como bool */
    private function marcasInicio(object $fila): void
    {
        $fila->pro_oferta_semana = (bool) $fila->pro_oferta_semana;
        $fila->pro_destacado     = (bool) $fila->pro_destacado;
    }

    /** Los publicados de la caché como jsonb para la base: [{pro_id, precio, precio_anterior, stock}, …] */
    private function publicadosJson(): string
    {
        $productos = $this->publicados()['productos'];

        return json_encode(array_map(fn ($id, $p) => ['pro_id' => $id] + $p, array_keys($productos), $productos));
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

    /** El JSON con el bloque 'auditoria' que piden las funciones de características (auditoria.logs_cambios). */
    private function jsonAuditado(array $datos): string
    {
        $u = auth('api')->user();

        return $this->json($datos + ['auditoria' => [
            'usuario_id'     => $u->id ?? null,
            'usuario_login'  => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'request_id'     => (string) Str::uuid(),
        ]]);
    }
}
