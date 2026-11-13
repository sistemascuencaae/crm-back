<?php

namespace App\Http\Controllers\inventarios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Tipos de producto: reemplaza a la pantalla de Dynamo (MntTipoProductoController).
// Las reglas y la auditoría (auditoria.logs_cambios) viven en las funciones inventarios.fn_tipo_producto_*.
class TipoProducto extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Código que lanza la función PG -> mensaje para el usuario.
    // Las cuentas de mal estado van antes que las normales: su código contiene al de la normal y gana la primera coincidencia.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'CAMPO_REQUERIDO:tpr_codigo' => 'Debe ingresar el código.',
        'CAMPO_LARGO:tpr_codigo' => 'El código no puede tener más de 10 caracteres.',
        'CAMPO_REQUERIDO:tpr_nombre' => 'Debe ingresar el nombre.',
        'CAMPO_LARGO:tpr_nombre' => 'El nombre no puede tener más de 200 caracteres.',
        'CAMPO_INVALIDO:tpr_porc_utilidad' => 'La utilidad debe ser un número positivo, con máximo 4 decimales.',
        'CAMPO_INVALIDO:tpr_movimiento' => 'El valor de Movimiento no es válido.',
        'CAMPO_INVALIDO:tpr_pordefecto' => 'El valor de Principal no es válido.',
        'CAMPO_INVALIDO:tpr_activo' => 'El valor de Activo no es válido.',
        'CAMPO_INVALIDO:tpr_reporta' => 'La categoría padre no es válida.',
        'CAMPO_INVALIDO:tpr_id' => 'El tipo de producto no es válido.',
        'TIPO_NO_EXISTE' => 'El tipo de producto no existe.',
        'TIPO_ES_PRINCIPAL' => 'No se puede eliminar la categoría Principal.',
        'TIPO_ACTIVO' => 'No se puede eliminar porque está activa: desactívela primero.',
        'PADRE_EN_PAPELERA' => 'No se puede restaurar porque su categoría padre está en la papelera: restáurela primero.',
        'TIPO_CON_PRODUCTOS' => 'No se puede eliminar porque tiene productos asignados.',
        'TIPO_CON_SUBCATEGORIAS' => 'No se puede eliminar porque tiene subcategorías.',
        'TIPO_EN_USO_SERIES' => 'No se puede eliminar porque tiene series registradas.',
        'TIPO_EN_USO_GEX' => 'No se puede eliminar porque está relacionada con una línea de garantía extendida.',
        'TIPO_EN_USO_FAMILIAS' => 'No se puede eliminar porque tiene familias de productos.',
        'TIPO_EN_USO_TIENDA' => 'No se puede eliminar porque se usa en un bloque de la tienda.',
        'TIPO_EN_USO_PARAMETROS' => 'No se puede eliminar porque tiene parámetros configurados.',
        'CAMPO_INVALIDO:cco_id' => 'Una de las cuentas contables no es válida.',
        'PADRE_NO_EXISTE' => 'La categoría padre no existe o está inactiva.',
        'PADRE_TRANSACCIONAL' => 'No se puede crear una subcategoría porque la categoría padre tiene movimiento.',
        'PADRE_CON_PRODUCTOS' => 'No se puede crear una subcategoría porque la categoría padre tiene productos asignados.',
        'CODIGO_REPETIDO' => 'Ya existe una categoría con ese código.',
        'NOMBRE_REPETIDO' => 'Ya existe una categoría con ese nombre.',
        'PRINCIPAL_EXISTE' => 'Ya existe una categoría marcada como Principal.',
        'PRINCIPAL_INACTIVA' => 'La categoría Principal no puede quedar inactiva.',
        'DESACTIVAR_CON_PRODUCTOS' => 'No se puede desactivar porque tiene productos asignados.',
        'DESACTIVAR_CON_SUBCATEGORIAS' => 'No se puede desactivar porque tiene subcategorías.',
        'PADRE_INACTIVO' => 'No se puede activar porque su categoría padre está inactiva.',
        'CUENTA_REQUERIDA:cco_id_inv' => 'Debe seleccionar la cuenta de inventario.',
        'CUENTA_REQUERIDA:cco_id_ing' => 'Debe seleccionar la cuenta de ingreso.',
        'CUENTA_REQUERIDA:cco_id_cos' => 'Debe seleccionar la cuenta de costo.',
        'CUENTA_REQUERIDA:cco_id_dev_com' => 'Debe seleccionar la cuenta de devolución en compras.',
        'CUENTA_REQUERIDA:cco_id_dev_vtas' => 'Debe seleccionar la cuenta de devolución en ventas.',
        'CUENTA_REQUERIDA:cco_id_desctos_vtas' => 'Debe seleccionar la cuenta de descuento en ventas.',
        'CUENTA_INVALIDA:cco_id_inv' => 'La cuenta de inventario no es válida: debe ser de movimiento y del módulo de inventarios.',
        'CUENTA_INVALIDA:cco_id_ing' => 'La cuenta de ingreso no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
        'CUENTA_INVALIDA:cco_id_cos' => 'La cuenta de costo no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
        'CUENTA_INVALIDA:cco_id_dev_com_mal_est' => 'La cuenta de devolución en compras en mal estado no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
        'CUENTA_INVALIDA:cco_id_dev_com' => 'La cuenta de devolución en compras no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
        'CUENTA_INVALIDA:cco_id_dev_vtas_mal_est' => 'La cuenta de devolución en ventas en mal estado no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
        'CUENTA_INVALIDA:cco_id_dev_vtas' => 'La cuenta de devolución en ventas no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
        'CUENTA_INVALIDA:cco_id_desctos_vtas' => 'La cuenta de descuento en ventas no es válida: debe ser activa, de movimiento y del módulo de contabilidad.',
    ];

    private const CAMPOS_CREAR = [
        'tpr_codigo',
        'tpr_nombre',
        'tpr_porc_utilidad',
        'tpr_movimiento',
        'tpr_pordefecto',
        'tpr_activo',
        'tpr_reporta',
        'cco_id_inv',
        'cco_id_ing',
        'cco_id_cos',
        'cco_id_dev_com',
        'cco_id_dev_com_mal_est',
        'cco_id_dev_vtas',
        'cco_id_dev_vtas_mal_est',
        'cco_id_desctos_vtas',
    ];

    private const REGLAS_CREAR = [
        'tpr_codigo' => 'required|string|max:10',
        'tpr_nombre' => 'required|string|max:200',
        'tpr_porc_utilidad' => 'nullable|numeric|min:0',
        'tpr_movimiento' => 'nullable|boolean',
        'tpr_pordefecto' => 'nullable|boolean',
        'tpr_activo' => 'nullable|boolean',
        'tpr_reporta' => 'nullable|integer',
        'cco_id_inv' => 'nullable|integer',
        'cco_id_ing' => 'nullable|integer',
        'cco_id_cos' => 'nullable|integer',
        'cco_id_dev_com' => 'nullable|integer',
        'cco_id_dev_com_mal_est' => 'nullable|integer',
        'cco_id_dev_vtas' => 'nullable|integer',
        'cco_id_dev_vtas_mal_est' => 'nullable|integer',
        'cco_id_desctos_vtas' => 'nullable|integer',
    ];

    // Igual que Dynamo: al editar solo cambian estos campos.
    private const CAMPOS_EDITAR = [
        'tpr_porc_utilidad',
        'tpr_pordefecto',
        'tpr_activo',
    ];

    private const REGLAS_EDITAR = [
        'tpr_porc_utilidad' => 'nullable|numeric|min:0',
        'tpr_pordefecto' => 'nullable|boolean',
        'tpr_activo' => 'nullable|boolean',
    ];

    public function crearTipoProducto(Request $request)
    {
        $validator = Validator::make($request->all(), self::REGLAS_CREAR);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_CREAR);

        // La regla acepta 1/0 y "1"/"0", pero la función solo recibe true o false.
        foreach (['tpr_movimiento', 'tpr_pordefecto', 'tpr_activo'] as $campo) {
            if (isset($payload[$campo])) {
                $payload[$campo] = filter_var($payload[$campo], FILTER_VALIDATE_BOOLEAN);
            }
        }

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_tipo_producto_registrar(?::jsonb) AS tpr_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tipo de producto creado con éxito', ['tpr_id' => $resultado->tpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el tipo de producto');
        }
    }

    // Crea un tipo nuevo a partir de otro: los campos del formulario llegan aquí y el resto se copia del origen.
    // El padre lo pone la función: siempre es el del origen.
    public function clonarTipoProducto(Request $request, int $tprId)
    {
        $validator = Validator::make($request->all(), self::REGLAS_CREAR);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_CREAR);

        // La regla acepta 1/0 y "1"/"0", pero la función solo recibe true o false.
        foreach (['tpr_movimiento', 'tpr_pordefecto', 'tpr_activo'] as $campo) {
            if (isset($payload[$campo])) {
                $payload[$campo] = filter_var($payload[$campo], FILTER_VALIDATE_BOOLEAN);
            }
        }

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_tipo_producto_clonar(?::jsonb) AS tpr_id', [json_encode($payload + ['tpr_id_origen' => $tprId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tipo de producto clonado con éxito', ['tpr_id' => $resultado->tpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al clonar el tipo de producto');
        }
    }

    public function editarTipoProducto(Request $request, $tprId)
    {
        $validator = Validator::make($request->all(), self::REGLAS_EDITAR);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_EDITAR);

        // La regla acepta 1/0 y "1"/"0", pero la función solo recibe true o false.
        foreach (['tpr_pordefecto', 'tpr_activo'] as $campo) {
            if (isset($payload[$campo])) {
                $payload[$campo] = filter_var($payload[$campo], FILTER_VALIDATE_BOOLEAN);
            }
        }

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_tipo_producto_edit(?::jsonb) AS tpr_id', [json_encode($payload + ['tpr_id' => (int) $tprId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tipo de producto modificado con éxito', ['tpr_id' => $resultado->tpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar el tipo de producto');
        }
    }

    // A la papelera: borrado lógico. No se puede vaciar.
    public function eliminarTipoProducto(Request $request, $tprId)
    {
        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_tipo_producto_papelera(?::jsonb) AS tpr_id', [json_encode(['tpr_id' => (int) $tprId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tipo de producto enviado a la papelera', ['tpr_id' => $resultado->tpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar el tipo de producto');
        }
    }

    public function restaurarTipoProducto(Request $request, $tprId)
    {
        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_tipo_producto_restaurar(?::jsonb) AS tpr_id', [json_encode(['tpr_id' => (int) $tprId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tipo de producto restaurado con éxito', ['tpr_id' => $resultado->tpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al restaurar el tipo de producto');
        }
    }

    public function papeleraTipoProducto(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM inventarios.fn_tipo_producto_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
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

    // Los datos de un tipo de producto para abrirlo en el formulario, con el código y el nombre de sus cuentas.
    public function buscarTipoProducto($tprId)
    {
        try {
            $fila = DB::selectOne('SELECT inventarios.fn_tipo_producto_buscar(?) AS datos', [(int) $tprId]);

            if (!$fila || $fila->datos === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'El tipo de producto no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Tipo de producto cargado con éxito', json_decode($fila->datos)));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el tipo de producto', $th->getMessage()));
        }
    }

    // Árbol de categorías, activas e inactivas; seleccionable indica cuáles pueden ser padre.
    public function arbolTipoProducto(Request $request)
    {
        try {
            $busqueda = trim((string) $request->query('busqueda', ''));
            $registros = DB::select('SELECT * FROM inventarios.fn_tipo_producto_arbol(?)', [$busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Árbol de tipos cargado con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el árbol de tipos', $th->getMessage()));
        }
    }

    // Cuentas contables para escoger en el formulario: todas, activas e inactivas, paginadas.
    public function listarCuentasContables(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM inventarios.fn_cuenta_contable_all(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Cuentas contables listadas con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las cuentas contables', $th->getMessage()));
        }
    }

    // Auditoría de UN tipo de producto: quién lo creó y modificó, y su historial paginado.
    public function auditoriaTipoProducto(Request $request)
    {
        try {
            $tprId = (int) $request->query('tpr_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($tprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Tipo de producto no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM inventarios.fn_tipo_producto_auditoria_resumen(?)', [$tprId]);
            $eventos = DB::select('SELECT * FROM inventarios.fn_tipo_producto_auditoria_listar_paginacion(?, ?, ?, ?)', [$tprId, $pagina, $tamanio, $busqueda]);
            $total = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del tipo de producto', $th->getMessage()));
        }
    }

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

    // Contexto del usuario para la auditoría forense: viaja dentro del jsonb y no se guarda como dato del tipo de producto.
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
