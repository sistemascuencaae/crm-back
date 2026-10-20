<?php

namespace App\Http\Controllers\inventarios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// CRUD de productos de Dynamo (public.producto). Las reglas y la bitácora del ERP
// viven en las funciones crm.fn_producto_* (PLANES/PRODUCTOS/produccion_productos.sql).
class ProductoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'CODIGO_REQUERIDO' => 'Debe ingresar el código del producto.',
        'CODIGO_MUY_LARGO' => 'El código no puede tener más de 15 caracteres.',
        'CODIGO_DUPLICADO' => 'Ya existe un producto con ese código.',
        'uk_producto_codigo' => 'Ya existe un producto con ese código.',
        'UNIDAD_NO_VALIDA' => 'La unidad no es válida o no está activa.',
        'NOMBRE_REQUERIDO' => 'Debe ingresar el nombre del producto.',
        'NOMBRE_MUY_LARGO' => 'El nombre no puede tener más de 500 caracteres.',
        'NOMBRE_DUPLICADO' => 'Ya existe un producto con ese nombre.',
        'TIPO_CON_OTRAS_CUENTAS' => 'No se puede cambiar a ese tipo: sus cuentas contables son distintas a las del tipo actual.',
        'TIPO_NO_VALIDO' => 'El tipo de producto no es válido: debe estar activo y ser del último nivel.',
        'MARCA_NO_VALIDA' => 'La marca no es válida o no está activa.',
        'IVA_NO_VALIDO' => 'Indique si el producto graba IVA.',
        'IVA_BLOQUEADO' => 'No se puede cambiar el IVA: el producto ya tiene movimientos o facturas.',
        'INVENTARIO_BLOQUEADO' => 'No se puede cambiar "Maneja inventario": el producto ya tiene movimientos o facturas.',
        'MEDIDA_NO_VALIDA' => 'El peso y las medidas no pueden ser negativos.',
        'COSTO_NEGOCIACION_NO_VALIDO' => 'El costo de negociación debe ser mayor a 0.',
        'PRODUCTO_CON_STOCK' => 'No se puede inactivar: el producto todavía tiene stock.',
        'PRODUCTO_BLOQUEADO' => 'El producto está abierto en edición en Dynamo. Intente de nuevo en unos minutos.',
        'PRODUCTO_NO_EXISTE' => 'El producto no existe.',
        'OBSERVACION_FACTURA_NO_VALIDA' => 'Elija qué datos pide la factura (MOTOS o GENERAL).',
        'USUARIO_SIN_DYNAMO' => 'Su usuario del CRM no tiene un usuario en Dynamo: no se puede registrar la bitácora.',
    ];

    private const CAMPOS_CREAR = [
        'pro_codigo',
        'pro_nombre',
        'tpr_id',
        'mar_id',
        'uni_id',
        'pro_impuesto',
        'pro_inventario',
        'pro_peso',
        'pro_largo',
        'pro_ancho',
        'costo_negociacion',
        'pro_observacion_factura',
        'obs_id',
    ];

    // El código y la unidad no se cambian al editar (igual que en Dynamo).
    private const CAMPOS_EDITAR = [
        'pro_nombre',
        'tpr_id',
        'mar_id',
        'pro_impuesto',
        'pro_inventario',
        'pro_activo',
        'pro_peso',
        'pro_largo',
        'pro_ancho',
        'costo_negociacion',
        'pro_observacion_factura',
        'obs_id',
    ];

    private const REGLAS_COMUNES = [
        'pro_nombre' => 'required|string|max:500',
        'tpr_id' => 'required|integer',
        'mar_id' => 'required|integer',
        'pro_impuesto' => 'required|boolean',
        'pro_inventario' => 'required|boolean',
        'pro_peso' => 'nullable|numeric|min:0',
        'pro_largo' => 'nullable|numeric|min:0',
        'pro_ancho' => 'nullable|numeric|min:0',
        'costo_negociacion' => 'nullable|numeric|gt:0',
        // Serie requerida al facturar: pro_observacion_factura + obs_id (MOTOS o GENERAL).
        'pro_observacion_factura' => 'required|boolean',
        'obs_id' => 'nullable|integer',
    ];

    // Árbol de tipos (niveles 1, 2 y 3) con los productos de cada rama; con búsqueda, solo las ramas que coinciden.
    public function arbolTiposProducto(Request $request)
    {
        try {
            $busqueda = trim((string) $request->query('busqueda', ''));
            $registros = DB::select('SELECT * FROM crm.fn_producto_arbol_tipos(?)', [$busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Árbol de tipos cargado con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el árbol de tipos', $th->getMessage()));
        }
    }

    // Productos paginados (los de un tipo con tpr_id). Busca cada palabra en código, nombre, marca, tipo o código de barras.
    public function listProductos(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $tprId = $request->query('tpr_id');
            $tprId = ($tprId === null || $tprId === '') ? null : (int) $tprId;

            $registros = DB::select('SELECT * FROM crm.fn_producto_listar_paginacion(?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $tprId]);
            $total = $registros[0]->total_registros ?? 0;

            // Stock global (todas las bodegas) solo de los productos de esta página.
            $ids = array_map(fn ($r) => (int) $r->pro_id, $registros);
            $stock = [];
            if ($ids) {
                foreach (DB::select('SELECT * FROM crm.fn_producto_stock_global_por_productos(?::integer[])', ['{' . implode(',', $ids) . '}']) as $s) {
                    $stock[(int) $s->pro_id] = $s->stock_actual;
                }
            }
            foreach ($registros as $r) {
                $r->stock_actual = $stock[(int) $r->pro_id] ?? 0;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Productos listados con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los productos', $th->getMessage()));
        }
    }

    // Combos del formulario: tipos (último nivel), marcas, unidades e IVA.
    public function catalogosProducto()
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_producto_catalogos() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Catálogos cargados con éxito', json_decode($fila->resultado, true)));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos', $th->getMessage()));
        }
    }

    // Ficha para editar + si tiene movimientos (bloquea IVA e inventario en la pantalla).
    public function getProducto($id)
    {
        try {
            $fila = DB::selectOne(
                'SELECT crm.fn_producto_buscar(?) AS producto, crm.fn_producto_tiene_movimientos(?)::int AS tiene_movimientos',
                [(int) $id, (int) $id]
            );

            if (!$fila || !$fila->producto) {
                return response()->json(RespuestaApi::returnResultado('error', 'El producto no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Producto cargado con éxito', [
                'producto' => json_decode($fila->producto, true),
                'tiene_movimientos' => (int) $fila->tiene_movimientos === 1,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el producto', $th->getMessage()));
        }
    }

    // Stock actual del producto en cada bodega (modal "Stock por bodega"), de mayor a menor.
    public function stockBodegasProducto($id)
    {
        try {
            $registros = DB::select('SELECT * FROM crm.fn_producto_stock_por_bodega(?)', [(int) $id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Stock cargado con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el stock del producto', $th->getMessage()));
        }
    }

    public function crearProducto(Request $request)
    {
        $validator = Validator::make($request->all(), self::REGLAS_COMUNES + [
            'pro_codigo' => 'required|string|max:15',
            'uni_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_CREAR);

        try {
            $resultado = DB::selectOne('SELECT crm.fn_producto_registrar(?::jsonb) AS pro_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Producto creado con éxito', ['pro_id' => $resultado->pro_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el producto');
        }
    }

    public function editarProducto(Request $request, $id)
    {
        $validator = Validator::make($request->all(), self::REGLAS_COMUNES + [
            'pro_activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_EDITAR);
        $payload['pro_id'] = (int) $id;

        try {
            $resultado = DB::selectOne('SELECT crm.fn_producto_modificar(?::jsonb) AS pro_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Producto modificado con éxito', ['pro_id' => $resultado->pro_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar el producto');
        }
    }

    // Auditoría de UN producto: resumen (quién creó / última modificación) + eventos paginados de auditoria.logs_cambios.
    public function productoAuditoria(Request $request)
    {
        try {
            $proId = (int) $request->query('pro_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($proId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Producto no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM crm.fn_producto_auditoria_resumen(?)', [$proId]);
            $eventos = DB::select('SELECT * FROM crm.fn_producto_auditoria_listar_paginacion(?, ?, ?, ?)', [$proId, $pagina, $tamanio, $busqueda]);
            $total = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del producto', $th->getMessage()));
        }
    }

    // errorInfo[2] trae solo el mensaje de PG (getMessage() incluye el SQL con el payload).
    private function errorTraducido(QueryException $e, string $porDefecto)
    {
        $texto = $e->errorInfo[2] ?? $e->getMessage();

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($texto, $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $e->getMessage()));
    }

    // Contexto del usuario para la auditoría forense y para el usuario de Dynamo de la bitácora.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}
