<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

// Productos de la tienda (públicos, sin login): el listado de /shop con el panel de filtros, las sugerencias del buscador
// del header y el detalle de un producto. La base decide todo (ecommerce.fn_producto_tienda_*: qué productos se ven, en
// qué orden, cuántos, las opciones del panel, los relacionados, si es moto…); aquí solo se revisa lo que llega, se
// llama a la base y se arma el JSON. Lo único que se agrega es la dirección de este servidor en las fotos (la tienda
// está en otro). Las respuestas no se guardan en caché.
// Reemplaza a paginaWeb/ProductoTiendaPublicoController del sistema viejo (que pasaba por ProductoTiendaService).
class ProductosTiendaController extends Controller
{
    // Productos por página que acepta el listado de /shop (la tienda pide 24)
    private const TAMANIOS = [12, 24, 48];

    // Filtros de características: como máximo cuántas características, cuántos valores en cada una y su largo
    private const CARACTERISTICAS_MAXIMO = 20;
    private const VALORES_MAXIMO = 30;
    private const VALOR_LARGO = 500;

    // Detalle: cuántos productos relacionados
    private const RELACIONADOS = 8;

    // Sugerencias del buscador: cuántas tarjetas y cuántas marcas
    private const SUGERENCIAS_PRODUCTOS = 3;
    private const SUGERENCIAS_MARCAS = 2;

    private const MENSAJES_FORMULARIO = [
        'pagina.integer'       => 'La página no es válida.',
        'pagina.min'           => 'La página no es válida.',
        'tamanio.in'           => 'La cantidad de productos por página no es válida.',
        'busqueda.required'    => 'Escribe lo que buscas.',
        'busqueda.string'      => 'La búsqueda no es válida.',
        'busqueda.max'         => 'La búsqueda es demasiado larga.',
        'categoria.integer'    => 'La categoría no es válida.',
        'categoria.min'        => 'La categoría no es válida.',
        'marca.integer'        => 'La marca no es válida.',
        'marca.min'            => 'La marca no es válida.',
        'grupo.integer'        => 'El grupo no es válido.',
        'grupo.min'            => 'El grupo no es válido.',
        'precio_min.numeric'   => 'El precio mínimo no es válido.',
        'precio_min.min'       => 'El precio mínimo no es válido.',
        'precio_min.max'       => 'El precio mínimo no es válido.',
        'precio_max.numeric'   => 'El precio máximo no es válido.',
        'precio_max.min'       => 'El precio máximo no es válido.',
        'precio_max.max'       => 'El precio máximo no es válido.',
        'orden.in'             => 'El orden no es válido.',
        'caracteristicas.json' => 'Los filtros de características no son válidos.',
        'caracteristicas.max'  => 'Hay demasiados filtros de características.',
    ];

    // GET public/productos?pagina=&tamanio=&busqueda=&categoria=&marca=&grupo=&precio_min=&precio_max=&orden=
    //     &caracteristicas={"car_id":["valor",…]}
    // Listado paginado de /shop (ecommerce.fn_producto_tienda_listar_paginacion):
    //   {registros: [tarjetas], total, pagina, tamanio,
    //    opciones (solo en la página 1, ecommerce.fn_producto_tienda_opciones; ver opciones()) | null,
    //    grupo (solo con filtro grupo): {gpr_id, nombre} | null}
    // La tienda carga la página siguiente con "Ver más productos".
    public function listar(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'pagina'          => 'nullable|integer|min:1',
            'tamanio'         => 'nullable|integer|in:' . implode(',', self::TAMANIOS),
            'busqueda'        => 'nullable|string|max:100',
            'categoria'       => 'nullable|integer|min:1',
            'marca'           => 'nullable|integer|min:1',
            'grupo'           => 'nullable|integer|min:1',
            'precio_min'      => 'nullable|numeric|min:0|max:9999999',
            'precio_max'      => 'nullable|numeric|min:0|max:9999999',
            'orden'           => 'nullable|in:recent,price_asc,price_desc',
            'caracteristicas' => 'nullable|string|json|max:20000',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $v = $validator->validated();
        $caracteristicas = $this->caracteristicas($v['caracteristicas'] ?? null);
        if ($caracteristicas === false) {
            return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_FORMULARIO['caracteristicas.json'], null));
        }
        $numero  = fn (string $clave) => isset($v[$clave]) && $v[$clave] !== '' ? $v[$clave] : null;
        $pagina  = (int) ($numero('pagina') ?? 1);
        $tamanio = (int) ($numero('tamanio') ?? 24);
        $grupo   = $numero('grupo') === null ? null : (int) $v['grupo'];

        // Los filtros, en el orden de las funciones de la base
        $filtros = [
            trim((string) ($v['busqueda'] ?? '')) ?: null,
            $numero('categoria') === null ? null : (int) $v['categoria'],
            $numero('marca') === null ? null : (int) $v['marca'],
            $grupo,
            $numero('precio_min') === null ? null : (float) $v['precio_min'],
            $numero('precio_max') === null ? null : (float) $v['precio_max'],
            $caracteristicas === null ? null : json_encode($caracteristicas, JSON_UNESCAPED_UNICODE),
        ];

        try {
            $registros = DB::select(
                'SELECT * FROM ecommerce.fn_producto_tienda_listar_paginacion(?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?)',
                [$pagina, $tamanio, ...$filtros, $v['orden'] ?? 'recent']
            );

            $resultado = [
                'registros' => array_map(fn ($f) => $this->tarjeta($f), $registros),
                'total'     => (int) ($registros[0]->total_registros ?? 0),
                'pagina'    => $pagina,
                'tamanio'   => $tamanio,
                'opciones'  => null,
            ];
            if ($pagina === 1) {
                [$resultado['opciones'], $grupoActivo] = $this->opciones($filtros);
                // Con filtro de grupo: su nombre (chip de /shop), o null si no existe o no está activo
                if ($grupo !== null) {
                    $resultado['grupo'] = $grupoActivo;
                }
            }

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Productos listados con éxito', $resultado))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron listar los productos de la tienda', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los productos', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/productos/sugerencias?busqueda=
    // Sugerencias del buscador del header mientras se escribe (ecommerce.fn_producto_tienda_sugerencias):
    //   {busqueda, total, categorias: [{tpr_id, nombre}] (el camino de la que tiene más productos),
    //    marcas: [{mar_id, nombre}], productos: [tarjetas]}
    public function sugerencias(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'busqueda' => 'required|string|max:100',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }
        $busqueda = trim($validator->validated()['busqueda']);

        try {
            $resultado = ['busqueda' => $busqueda, 'total' => 0, 'categorias' => [], 'marcas' => [], 'productos' => []];
            $filas = DB::select(
                'SELECT * FROM ecommerce.fn_producto_tienda_sugerencias(?, ?, ?)',
                [$busqueda, self::SUGERENCIAS_PRODUCTOS, self::SUGERENCIAS_MARCAS]
            );
            foreach ($filas as $f) {
                match ($f->tipo) {
                    'total'     => $resultado['total'] = (int) $f->cantidad,
                    'categoria' => $resultado['categorias'][] = ['tpr_id' => (int) $f->id, 'nombre' => $f->nombre],
                    'marca'     => $resultado['marcas'][] = ['mar_id' => (int) $f->id, 'nombre' => $f->nombre],
                    'producto'  => $resultado['productos'][] = $this->tarjeta((object) (['pro_id' => $f->id, 'pro_nombre' => $f->nombre] + (array) $f)),
                    default     => null,
                };
            }

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Sugerencias', $resultado))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudieron cargar las sugerencias de la tienda', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las sugerencias', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    // GET public/productos/{proId}   → {producto, relacionados}
    // El producto sale de ecommerce.fn_producto_tienda_detalle (solo si se vende en la tienda) y los relacionados de
    // ecommerce.fn_producto_tienda_relacionados. Si no se vende: code 404 en el cuerpo (HTTP 200), status 'error' y data
    // null; la tienda muestra "Producto no disponible". Una falla interna responde code 500 en el cuerpo (también
    // HTTP 200): así la tienda los distingue.
    public function detalle(int $proId)
    {
        try {
            $p = DB::selectOne('SELECT * FROM ecommerce.fn_producto_tienda_detalle(?)', [$proId]);

            if ($p === null) {
                // RespuestaApi no deja elegir el código del error (siempre 500): se reemplaza solo el code
                return response()
                    ->json(['code' => 404] + RespuestaApi::returnResultado('error', 'Producto no disponible', null))
                    ->header('Cache-Control', 'no-cache');
            }

            $relacionados = DB::select('SELECT * FROM ecommerce.fn_producto_tienda_relacionados(?, ?)', [$proId, self::RELACIONADOS]);

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Producto', [
                    'producto'     => [
                        'pro_id'          => (int) $p->pro_id,
                        'codigo'          => $p->codigo,
                        'nombre'          => $p->nombre,
                        'descripcion'     => $p->descripcion,
                        // La descripción completa (documento del CRM, HTML ya limpio); null si no tiene → se usa
                        // descripcion. Sus imágenes con la dirección de este servidor.
                        'descripcion_html' => $p->descripcion_html === null ? null : str_replace(
                            'src="/api/public/pagina-web/imagenes/productos/',
                            'src="' . url('api/public/pagina-web/imagenes/productos') . '/',
                            $p->descripcion_html
                        ),
                        'tpr_id'          => (int) $p->tpr_id,
                        'categoria'       => $p->categoria,
                        'destacado'       => (bool) $p->destacado,
                        'imagenes'        => $this->imagenes($p->imagenes),
                        'ficha'           => json_decode($p->ficha ?? '[]', true) ?? [],
                        'precio'          => $p->precio !== null ? (float) $p->precio : null,
                        'precio_anterior' => $p->precio_anterior !== null ? (float) $p->precio_anterior : null,
                        'es_moto'         => (bool) $p->es_moto,
                        'camino'          => json_decode($p->camino ?? '[]', true) ?? [],
                        'marca'           => $p->mar_id === null ? null : [
                            'mar_id' => (int) $p->mar_id,
                            'nombre' => $p->marca,
                            'logo'   => $p->marca_logo,
                            'enlace' => $p->marca_enlace,
                        ],
                    ],
                    'relacionados' => array_map(fn ($f) => $this->tarjeta($f), $relacionados),
                ]))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar el producto', ['pro_id' => $proId, 'error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el producto', null))
                ->header('Cache-Control', 'no-store');
        }
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * Opciones del panel de filtros tal como las devuelve la base (ecommerce.fn_producto_tienda_opciones), ya elegidas
     * y en su orden; aquí solo se agrupan:
     *   [{categorias: árbol del panel por relevancia [{tpr_id, nombre, cantidad, elegida, abierta, hijas: [...]}],
     *     camino: [{tpr_id, nombre}], marcas: [{mar_id, nombre, cantidad}], precio: {min, max} | null,
     *     caracteristicas: [{car_id, nombre, unidad, tipo, valores: [{clave, nombre, cantidad, marcado}]}],
     *     ignorados: ['categoria' | 'marca' | 'grupo']},
     *    grupo activo {gpr_id, nombre} | null]
     */
    private function opciones(array $filtros): array
    {
        $opciones = ['categorias' => [], 'camino' => [], 'marcas' => [], 'precio' => null, 'caracteristicas' => [], 'ignorados' => []];
        $grupo    = null;
        // Posición de cada característica en la lista (los valores se agregan a la suya)
        $posicion = [];
        // Categorías del árbol (por id) y en qué orden llegaron, con su padre
        $categorias = [];
        $llegada    = [];

        foreach (DB::select('SELECT * FROM ecommerce.fn_producto_tienda_opciones(?, ?, ?, ?, ?, ?, ?::jsonb)', $filtros) as $o) {
            switch ($o->tipo) {
                case 'categoria':
                    $categorias[(int) $o->id] = [
                        'tpr_id'   => (int) $o->id,
                        'nombre'   => $o->nombre,
                        'cantidad' => (int) $o->cantidad,
                        'elegida'  => (bool) $o->marcado,
                        'abierta'  => (bool) $o->abierta,
                        'hijas'    => [],
                    ];
                    $llegada[] = [(int) $o->id, $o->padre_id === null ? null : (int) $o->padre_id];
                    break;
                case 'camino':
                    $opciones['camino'][] = ['tpr_id' => (int) $o->id, 'nombre' => $o->nombre];
                    break;
                case 'marca':
                    $opciones['marcas'][] = ['mar_id' => (int) $o->id, 'nombre' => $o->nombre, 'cantidad' => (int) $o->cantidad];
                    break;
                case 'precio':
                    $opciones['precio'] = ['min' => (float) $o->minimo, 'max' => (float) $o->maximo];
                    break;
                case 'grupo':
                    $grupo = ['gpr_id' => (int) $o->id, 'nombre' => $o->nombre];
                    break;
                case 'ignorado':
                    $opciones['ignorados'][] = $o->nombre;
                    break;
                case 'caracteristica':
                    $posicion[(int) $o->id] = count($opciones['caracteristicas']);
                    $opciones['caracteristicas'][] = [
                        'car_id'  => (int) $o->id,
                        'nombre'  => $o->nombre,
                        'unidad'  => $o->unidad,
                        'tipo'    => $o->car_tipo,
                        'valores' => [],
                    ];
                    break;
                case 'valor':
                    // La base manda las características antes que sus valores ('caracteristica' < 'valor')
                    if (isset($posicion[(int) $o->id])) {
                        $opciones['caracteristicas'][$posicion[(int) $o->id]]['valores'][] = [
                            'clave'    => $o->clave,
                            'nombre'   => $o->nombre,
                            'cantidad' => (int) $o->cantidad,
                            'marcado'  => (bool) $o->marcado,
                        ];
                    }
                    break;
            }
        }

        // El árbol: la base manda cada nivel después del de arriba y las hermanas en su orden. Se recorre al revés para
        // que cada categoría ya tenga sus hijas al colgarla de su padre (array_unshift mantiene el orden de la base)
        for ($i = count($llegada) - 1; $i >= 0; $i--) {
            [$id, $padre] = $llegada[$i];
            if ($padre !== null && isset($categorias[$padre])) {
                array_unshift($categorias[$padre]['hijas'], $categorias[$id]);
            } else {
                array_unshift($opciones['categorias'], $categorias[$id]);
            }
        }

        return [$opciones, $grupo];
    }

    /**
     * {"car_id": ["valor", …]} revisado: un objeto con ids numéricos y listas de textos (sin vacíos ni repetidos).
     * null si no viene o queda vacío; false si no tiene esa forma. Qué características y valores valen lo decide la base.
     */
    private function caracteristicas(?string $json): array|null|false
    {
        if ($json === null || $json === '') {
            return null;
        }
        $datos = json_decode($json, true);
        if (!is_array($datos) || ($datos !== [] && array_is_list($datos)) || count($datos) > self::CARACTERISTICAS_MAXIMO) {
            return false;
        }

        $limpias = [];
        foreach ($datos as $carId => $valores) {
            if (!preg_match('/^\d{1,9}$/', (string) $carId) || !is_array($valores) || !array_is_list($valores)
                || count($valores) > self::VALORES_MAXIMO) {
                return false;
            }
            foreach ($valores as $valor) {
                if (!is_string($valor) || mb_strlen($valor) > self::VALOR_LARGO) {
                    return false;
                }
            }
            $valores = array_values(array_unique(array_filter(array_map('trim', $valores), fn ($x) => $x !== '')));
            if ($valores) {
                $limpias[(string) $carId] = $valores;
            }
        }

        return $limpias ?: null;
    }

    /**
     * La tarjeta de un producto, de una fila de las funciones de la tienda (traen precio, stock y es_moto):
     *   {pro_id, codigo, nombre, marca, tpr_id, categoria, imagenes: [url…], destacado, oferta, precio, precio_anterior,
     *    stock, es_moto}
     */
    private function tarjeta(object $f): array
    {
        return [
            'pro_id'          => (int) $f->pro_id,
            'codigo'          => $f->pro_codigo,
            'nombre'          => $f->pro_nombre,
            'marca'           => $f->marca,
            'tpr_id'          => (int) $f->tpr_id,
            'categoria'       => $f->categoria,
            'imagenes'        => $this->imagenes($f->imagenes),
            'destacado'       => (bool) $f->destacado,
            'oferta'          => (bool) ($f->oferta ?? false),
            'precio'          => $f->precio !== null ? (float) $f->precio : null,
            'precio_anterior' => $f->precio_anterior !== null ? (float) $f->precio_anterior : null,
            'stock'           => (float) $f->stock,
            'es_moto'         => (bool) ($f->es_moto ?? false),
        ];
    }

    /**
     * Las fotos que arma la base (inventarios.fn_producto_imagen_ruta: /api/public/pagina-web/imagenes/productos/…) con
     * la dirección de este servidor, porque la tienda está en otro.
     */
    private function imagenes(?string $json): array
    {
        $rutas = json_decode($json ?? '[]', true);

        return is_array($rutas) ? array_map(fn ($ruta) => url($ruta), $rutas) : [];
    }
}
