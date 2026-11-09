<?php

namespace App\Http\Controllers\inventarios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Características de los productos: la plantilla de cada categoría de tercer nivel y sus valores en cada producto.
// Las reglas y la auditoría (auditoria.logs_cambios) viven en las funciones inventarios.fn_caracteristica_* y
// inventarios.fn_producto_ficha_guardar, que también usa la pantalla de la tienda.
class CaracterísticasProductoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'CATEGORIA_NO_EXISTE' => 'La categoría no existe o está inactiva.',
        'CATEGORIA_NO_TRANSACCIONAL' => 'Las características solo van en categorías de tercer nivel.',
        'CARACTERISTICA_NO_EXISTE' => 'La característica no existe.',
        'CARACTERISTICA_REPETIDA' => 'Ya hay una característica activa con ese nombre en esta categoría.',
        'CARACTERISTICAS_MAXIMO' => 'Una categoría puede tener como máximo 30 características.',
        'CARACTERISTICA_TIPO_EN_USO' => 'Ya hay productos con este dato lleno: el tipo no se puede cambiar.',
        'CARACTERISTICA_EN_FAMILIA' => 'Una familia usa esta característica. Cámbiela en la familia antes de eliminarla.',
        'ORDEN_INVALIDO' => 'El nuevo orden no es válido.',
        'ORDEN_DESACTUALIZADO' => 'La lista cambió mientras la ordenaba. Vuelva a abrirla e inténtelo de nuevo.',
        'CAMPO_REQUERIDO:car_nombre' => 'Debe ingresar el nombre de la característica.',
        'CAMPO_LARGO:car_nombre' => 'El nombre no puede tener más de 60 caracteres.',
        'CAMPO_LARGO:car_unidad' => 'La unidad no puede tener más de 15 caracteres.',
        'CAMPO_INVALIDO:car_tipo' => 'El tipo debe ser texto o número.',
        // Valores de la ficha (inventarios.fn_producto_ficha_guardar)
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

    private const NIVEL_TRANSACCIONAL = 3;

    private const CAMPOS_CREAR = [
        'tpr_id',
        'car_nombre', 
        'car_tipo', 
        'car_unidad'
    ];

    // La categoría no se cambia al editar.
    private const CAMPOS_EDITAR = [
        'car_nombre', 
        'car_tipo', 
        'car_unidad'
    ];

    private const CAMPOS_FICHA = [
        'valores',
        'sueltas',
        'orden'
    ];

    private const REGLAS_COMUNES = [
        'car_nombre' => 'required|string|max:60',
        'car_tipo' => 'required|in:texto,numero',
        'car_unidad' => 'nullable|string|max:15',
    ];

    // Árbol de tipos (el mismo de Productos): transaccional = categoría de tercer nivel, la única que lleva características.
    public function arbolTiposProducto(Request $request)
    {
        try {
            $busqueda = trim((string) $request->query('busqueda', ''));
            $registros = DB::select('SELECT * FROM crm.fn_producto_arbol_tipos(?)', [$busqueda]);

            foreach ($registros as $r) {
                $r->transaccional = (int) $r->tpr_nivel === self::NIVEL_TRANSACCIONAL;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Árbol de tipos cargado con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el árbol de tipos', $th->getMessage()));
        }
    }

    // Características activas de una categoría, en su orden. Con búsqueda, las que coinciden por nombre.
    public function listCaracteristicas(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 100), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $tprId = $request->query('tpr_id');
            $tprId = ($tprId === null || $tprId === '') ? null : (int) $tprId;

            $registros = DB::select('SELECT * FROM inventarios.fn_caracteristica_listar_paginacion(?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $tprId]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Características listadas con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las características', $th->getMessage()));
        }
    }

    // Características en la papelera de una categoría.
    public function papeleraCaracteristicas(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $tprId = $request->query('tpr_id');
            $tprId = ($tprId === null || $tprId === '') ? null : (int) $tprId;

            $registros = DB::select('SELECT * FROM inventarios.fn_caracteristica_papelera_paginacion(?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $tprId]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    public function getCaracteristica($id)
    {
        try {
            $fila = DB::selectOne('SELECT inventarios.fn_caracteristica_buscar(?) AS caracteristica', [(int) $id]);

            if (!$fila || !$fila->caracteristica) {
                return response()->json(RespuestaApi::returnResultado('error', 'La característica no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Característica cargada con éxito', [
                'caracteristica' => json_decode($fila->caracteristica, true),
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la característica', $th->getMessage()));
        }
    }

    public function crearCaracteristica(Request $request)
    {
        $validator = Validator::make($request->all(), self::REGLAS_COMUNES + [
            'tpr_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_CREAR);

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_caracteristica_registrar(?::jsonb) AS car_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica creada con éxito', ['car_id' => $resultado->car_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la característica');
        }
    }

    // Crea una característica nueva a partir de otra: los campos del formulario llegan aquí y la función valida el
    // origen y aplica las mismas reglas del alta. La categoría no viaja: la pone la función, siempre la del origen.
    public function clonarCaracteristica(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), self::REGLAS_COMUNES);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_EDITAR) + ['car_id_origen' => $id];

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_caracteristica_clonar(?::jsonb) AS car_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica clonada con éxito', ['car_id' => $resultado->car_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al clonar la característica');
        }
    }

    public function editarCaracteristica(Request $request, $id)
    {
        $validator = Validator::make($request->all(), self::REGLAS_COMUNES);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_EDITAR);
        $payload['car_id'] = (int) $id;

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_caracteristica_modificar(?::jsonb) AS car_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica modificada con éxito', ['car_id' => $resultado->car_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar la característica');
        }
    }

    // {tpr_id, ids: [car_id…]}: todas las de la categoría, en el nuevo orden.
    public function ordenarCaracteristicas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tpr_id' => 'required|integer',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(['tpr_id', 'ids']);

        try {
            DB::selectOne('SELECT inventarios.fn_caracteristica_ordenar(?::jsonb)', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar las características');
        }
    }

    // A la papelera: los valores de los productos se conservan.
    public function eliminarCaracteristica(Request $request, $id)
    {
        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_caracteristica_eliminar(?::jsonb) AS car_id', [json_encode(['car_id' => (int) $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica enviada a la papelera', ['car_id' => $resultado->car_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar la característica');
        }
    }

    public function restaurarCaracteristica(Request $request, $id)
    {
        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_caracteristica_restaurar(?::jsonb) AS car_id', [json_encode(['car_id' => (int) $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica restaurada con éxito', ['car_id' => $resultado->car_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al restaurar la característica');
        }
    }

    // Borra para siempre la papelera de la categoría, con los valores que tenían los productos.
    public function vaciarCaracteristicas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tpr_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_caracteristica_vaciar(?::jsonb) AS cantidad', [json_encode(['tpr_id' => (int) $request->input('tpr_id'), 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada con éxito', ['cantidad' => (int) $resultado->cantidad]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al vaciar la papelera');
        }
    }

    // Auditoría de UNA característica: resumen (quién creó / última modificación) + eventos paginados de auditoria.logs_cambios.
    public function caracteristicaAuditoria(Request $request)
    {
        try {
            $carId = (int) $request->query('car_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($carId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Característica no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM inventarios.fn_caracteristica_auditoria_resumen(?)', [$carId]);
            $eventos = DB::select('SELECT * FROM inventarios.fn_caracteristica_auditoria_listar_paginacion(?, ?, ?, ?)', [$carId, $pagina, $tamanio, $busqueda]);
            $total = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la característica', $th->getMessage()));
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

    // Contexto del usuario para la auditoría forense: viaja dentro del jsonb y no se guarda como dato de la característica.
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
