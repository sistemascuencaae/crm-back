<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Ecommerce > Contenido de productos: marketing pone las imágenes, la ficha y la descripción completa de los productos
// que la tienda puede vender (stock vendible mayor a 0), sin entrar a Inventarios > Productos.
//   - Aquí: el árbol de tipos, la lista (con lo que le falta a cada producto), la ficha (antes en
//     inventarios/CaracterísticasProductoController) y la auditoría general de un producto (ficha, imágenes,
//     descripción y vista 360° en una sola lista).
//   - Las imágenes y la descripción completa tienen su propio controlador (ecommerce/ProductoImagenesController y
//     ecommerce/ProductoDescripcionController, antes en inventarios/).
// Las reglas viven en ecommerce.fn_contenido_producto_* (database/sql/contenido_productos.sql) y en
// inventarios.fn_producto_ficha_* (la ficha, con su auditoría).
class ContenidoProductosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Código que lanza la función PG -> mensaje para el usuario (inventarios.fn_producto_ficha_guardar).
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'PRODUCTO_NO_EXISTE' => 'El producto no existe o está inactivo.',
        'FICHA_INVALIDA' => 'Los datos de la ficha no tienen un formato válido.',
        'FICHA_DESACTUALIZADA' => 'Las características de esta categoría cambiaron. Vuelva a abrir el producto.',
        'CAMPO_NUMERO' => 'Escriba solo un número (por ejemplo 150 o 150.5).',
        'SUELTA_REPETIDA' => 'Ese nombre ya está en la ficha.',
        'SUELTAS_MAXIMO' => 'Se pueden agregar como máximo 20 datos sueltos.',
        'CAMPO_REQUERIDO:sueltas' => 'Cada dato suelto necesita nombre y valor.',
        'CAMPO_LARGO:sueltas' => 'El nombre de un dato suelto admite 60 caracteres y su valor 500.',
        'CAMPO_LARGO:valores' => 'Un valor de la ficha admite máximo 500 caracteres.',
    ];

    private const CAMPOS_FICHA = ['valores', 'sueltas', 'orden'];

    // Los filtros de "qué le falta" para el combo -> ecommerce.fn_contenido_producto_faltas.
    public function faltas()
    {
        try {
            $fila = DB::selectOne('SELECT ecommerce.fn_contenido_producto_faltas() AS f');

            return response()->json(RespuestaApi::returnResultado('success', 'Filtros cargados con éxito', json_decode($fila->f, true)));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los filtros', $th->getMessage()));
        }
    }

    // Árbol de tipos con cuántos productos (con stock vendible) tiene cada rama -> ecommerce.fn_contenido_producto_arbol_tipos.
    // GET ?falta=  (el mismo filtro de la lista: los conteos lo siguen)
    public function arbol(Request $request)
    {
        try {
            $falta = trim((string) $request->query('falta', '')) ?: null;
            $registros = DB::select('SELECT * FROM ecommerce.fn_contenido_producto_arbol_tipos(?)', [$falta]);

            return response()->json(RespuestaApi::returnResultado('success', 'Árbol de tipos cargado con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el árbol de tipos', $th->getMessage()));
        }
    }

    // Productos con stock vendible, paginados, con lo que le falta a cada uno -> ecommerce.fn_contenido_producto_listar_paginacion.
    // GET ?pagina=&tamanio=&busqueda=&tpr_id=&falta=
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $tprId = $request->query('tpr_id');
            $tprId = ($tprId === null || $tprId === '') ? null : (int) $tprId;
            $falta = trim((string) $request->query('falta', '')) ?: null;

            $registros = DB::select('SELECT * FROM ecommerce.fn_contenido_producto_listar_paginacion(?, ?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $tprId, $falta]);

            return response()->json(RespuestaApi::returnResultado('success', 'Productos listados con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los productos', $th->getMessage()));
        }
    }

    // Auditoría general de UN producto: todo lo que se hizo con su contenido (ficha, imágenes, descripción y vista 360°),
    // el más nuevo primero -> ecommerce.fn_contenido_producto_auditoria_listar_paginacion. GET ?pro_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $proId = (int) $request->query('pro_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($proId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Producto no válido', null));
            }

            $eventos = DB::select('SELECT * FROM ecommerce.fn_contenido_producto_auditoria_listar_paginacion(?, ?, ?, ?)', [$proId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del producto', $th->getMessage()));
        }
    }

    // La ficha de un producto en su orden: las características de su categoría con sus valores, y sus datos sueltos.
    // orden_propio: el producto tiene su propio orden; si no, va el de la categoría y después los datos sueltos.
    public function fichaProducto($id)
    {
        try {
            $ficha = DB::select('SELECT * FROM inventarios.fn_producto_ficha_ordenada(?)', [(int) $id]);
            $ordenPropio = (bool) ($ficha[0]->orden_propio ?? false);

            foreach ($ficha as $fila) {
                unset($fila->orden_propio);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Ficha cargada con éxito', ['ficha' => $ficha, 'orden_propio' => $ordenPropio]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la ficha del producto', $th->getMessage()));
        }
    }

    // {valores: {car_id: valor}, sueltas: [{nombre, valor}], orden: ['c:45', 's:Incluye']}. Un valor vacío lo borra;
    // sueltas reemplaza todos los del producto; orden null vuelve al de la categoría y, si no viene, no se toca.
    public function guardarFichaProducto(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'valores' => 'sometimes|array',
            'sueltas' => 'sometimes|array',
            'orden' => 'nullable|array',
            'orden.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_FICHA);
        $payload['pro_id'] = (int) $id;

        // {} vacío llega a PHP como [] y se enviaría como arreglo: debe ir como objeto.
        if (array_key_exists('valores', $payload)) {
            $payload['valores'] = (object) $payload['valores'];
        }

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_producto_ficha_guardar(?::jsonb) AS pro_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Ficha guardada con éxito', ['pro_id' => $resultado->pro_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la ficha');
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

    // Contexto del usuario para la auditoría forense (auditoria.fn_registrar_evento).
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
