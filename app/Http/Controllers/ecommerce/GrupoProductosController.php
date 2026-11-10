<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Grupos de productos (ecommerce.grupos_productos y sus productos en ecommerce.grupo_productos_detalles): los productos
// DISPONIBLES para armarlos (activos Y con stock), paginados, todos o por tipo o por marca; crear, editar, clonar,
// papelera (borrado lógico) y auditoría.
// Las reglas viven en ecommerce.fn_grupo_producto(s)_* (database/sql/grupo_productos.sql).
class GrupoProductosController extends Controller
{
    private const MENSAJES_VALIDACION = [
        'integer' => 'El valor de :attribute no es válido.',
        'min' => 'El valor de :attribute no es válido.',
    ];

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'REQUIERE_NOMBRE' => 'Debe ingresar el nombre del grupo.',
        'NOMBRE_LARGO' => 'El nombre no puede tener más de 80 caracteres.',
        'GRUPO_DUPLICADO' => 'Ya existe un grupo con ese nombre (si está restaurando, cambie primero el nombre del otro grupo).',
        'ux_grupos_productos_nombre' => 'Ya existe un grupo con ese nombre.',
        'DESCRIPCION_LARGA' => 'La descripción no puede tener más de 250 caracteres.',
        'VALOR_INVALIDO' => 'El valor de "Activo" no es válido.',
        'REQUIERE_PRODUCTOS' => 'Elija al menos un producto para el grupo (al clonar todo: ningún producto del grupo original sigue activo y con stock).',
        'PRODUCTO_INVALIDO' => 'Uno de los productos elegidos no es válido.',
        'PRODUCTO_NO_EXISTE' => 'Uno de los productos elegidos no existe.',
        'PRODUCTO_NO_DISPONIBLE' => 'Uno de los productos elegidos ya no está activo o se quedó sin stock. Vuelva a cargar la lista.',
        'GRUPO_NO_EXISTE' => 'El grupo no existe o está en la papelera.',
        'GRUPO_NO_EN_PAPELERA' => 'El grupo no está en la papelera (puede que otro usuario ya lo restauró).',
    ];

    // Mensajes de la validación del formulario (crear y editar).
    private const MENSAJES_FORMULARIO = [
        'gpr_nombre.required' => 'Debe ingresar el nombre del grupo.',
        'gpr_nombre.max' => 'El nombre no puede tener más de 80 caracteres.',
        'gpr_descripcion.max' => 'La descripción no puede tener más de 250 caracteres.',
        'gpr_activo.boolean' => 'El valor de "Activo" no es válido.',
        'productos.required' => 'Elija al menos un producto para el grupo.',
        'productos.array' => 'La lista de productos no es válida.',
        'productos.min' => 'Elija al menos un producto para el grupo.',
        'productos.*.integer' => 'Uno de los productos elegidos no es válido.',
        'productos.*.min' => 'Uno de los productos elegidos no es válido.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'gpr_nombre',
        'gpr_descripcion',
        'gpr_activo',
        'productos',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Todos los productos disponibles (activos y con stock), sin filtrar por tipo ni marca. GET ?pagina=&tamanio=&busqueda=
    public function productosDisponibles(Request $request)
    {
        return $this->listarDisponibles($request, null, null);
    }

    // Productos disponibles de un tipo (incluye sus subtipos). GET ?tpr_id=&pagina=&tamanio=&busqueda=
    public function productosPorTipo(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'tpr_id' => 'required|integer|min:1',
        ], self::MENSAJES_VALIDACION + ['tpr_id.required' => 'Elija el tipo de producto.'], ['tpr_id' => 'tipo de producto']);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        return $this->listarDisponibles($request, (int) $request->query('tpr_id'), null);
    }

    // Productos disponibles de una marca (incluye sus submarcas). GET ?mar_id=&pagina=&tamanio=&busqueda=
    public function productosPorMarca(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'mar_id' => 'required|integer|min:1',
        ], self::MENSAJES_VALIDACION + ['mar_id.required' => 'Elija la marca.'], ['mar_id' => 'marca']);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        return $this->listarDisponibles($request, null, (int) $request->query('mar_id'));
    }

    // Crea un grupo con los productos elegidos, en ese orden -> ecommerce.fn_grupo_producto_add.
    // Body: { gpr_nombre, gpr_descripcion?, gpr_activo?, productos: [pro_id, ...] }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gpr_nombre' => 'required|string|max:80',
            'gpr_descripcion' => 'nullable|string|max:250',
            'gpr_activo' => 'nullable|boolean',
            'productos' => 'required|array|min:1',
            'productos.*' => 'required|integer|min:1',
        ], self::MENSAJES_FORMULARIO);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);

        try {
            // El bloque 'auditoria' va dentro del jsonb: la función lo usa para la auditoría forense y para el usu_id.
            $resultado = DB::selectOne('SELECT ecommerce.fn_grupo_producto_add(?::jsonb) AS gpr_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo creado con éxito', ['gpr_id' => $resultado->gpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el grupo');
        }
    }

    // Modifica el grupo -> ecommerce.fn_grupo_producto_edit. productos es la lista COMPLETA en su orden (los que no vienen
    // salen del grupo). gpr_descripcion y gpr_activo son opcionales: si no vienen, conservan su valor. Al editar, solo los
    // productos que se agregan tienen que estar activos y con stock.
    // Body: { gpr_nombre, gpr_descripcion?, gpr_activo?, productos: [pro_id, ...] }
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'gpr_nombre' => 'required|string|max:80',
            'gpr_descripcion' => 'nullable|string|max:250',
            'gpr_activo' => 'sometimes|boolean',
            'productos' => 'required|array|min:1',
            'productos.*' => 'required|integer|min:1',
        ], self::MENSAJES_FORMULARIO);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);
        $payload['gpr_id'] = $id;

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_grupo_producto_edit(?::jsonb) AS gpr_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo modificado con éxito', ['gpr_id' => $resultado->gpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar el grupo');
        }
    }

    // Crea un grupo nuevo a partir de otro -> ecommerce.fn_grupo_producto_clonar. Los campos del formulario llegan aquí; la
    // descripción y "activo", si no vienen, se copian del origen. productos = los que el usuario dejó, en su orden; si no
    // viene, se clona todo (los del origen que siguen activos y con stock).
    // Body: { gpr_nombre, gpr_descripcion?, gpr_activo?, productos?: [pro_id, ...] }
    public function clonar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'gpr_nombre' => 'required|string|max:80',
            'gpr_descripcion' => 'nullable|string|max:250',
            'gpr_activo' => 'sometimes|boolean',
            'productos' => 'sometimes|required|array|min:1',
            'productos.*' => 'required|integer|min:1',
        ], self::MENSAJES_FORMULARIO);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD) + ['gpr_id_origen' => $id];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_grupo_producto_clonar(?::jsonb) AS gpr_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo clonado con éxito', ['gpr_id' => $resultado->gpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al clonar el grupo');
        }
    }

    // A la papelera: borrado lógico -> ecommerce.fn_grupo_producto_papelera. No se puede vaciar.
    public function eliminar(Request $request, int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_grupo_producto_papelera(?::jsonb) AS gpr_id', [json_encode(['gpr_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo enviado a la papelera', ['gpr_id' => $resultado->gpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar el grupo');
        }
    }

    // Vuelve de la papelera tal como estaba -> ecommerce.fn_grupo_producto_restaurar.
    public function restaurar(Request $request, int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_grupo_producto_restaurar(?::jsonb) AS gpr_id', [json_encode(['gpr_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo restaurado con éxito', ['gpr_id' => $resultado->gpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al restaurar el grupo');
        }
    }

    // Grupos en la papelera -> ecommerce.fn_grupo_producto_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_grupo_producto_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    // Auditoría de UN grupo (también de los de la papelera): resumen (quién lo creó / última modificación) + eventos
    // paginados de auditoria.logs_cambios. GET ?gpr_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $gprId = (int) $request->query('gpr_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($gprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Grupo no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_grupo_producto_auditoria_resumen(?)', [$gprId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_grupo_producto_auditoria_listar_paginacion(?, ?, ?, ?)', [$gprId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del grupo', $th->getMessage()));
        }
    }

    // La lista paginada común -> ecommerce.fn_grupo_productos_listar_paginacion (cada fila trae su stock_actual).
    private function listarDisponibles(Request $request, ?int $tprId, ?int $marId)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM ecommerce.fn_grupo_productos_listar_paginacion(?, ?, ?, ?, ?)',
                [$pagina, $tamanio, $busqueda, $tprId, $marId]
            );

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
