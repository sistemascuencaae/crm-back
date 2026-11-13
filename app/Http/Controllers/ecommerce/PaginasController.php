<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Páginas de la tienda (ecommerce.paginas): el "cuerpo" donde se ponen las secciones. Listar, ver, crear, editar,
// clonar (con sus secciones), papelera (borrado lógico) y auditoría. Las secciones están en SeccionesController.
// Las reglas viven en ecommerce.fn_pagina_* (database/sql/paginas.sql, pagina_edit.sql, pagina_papelera.sql,
// pagina_listar.sql y pagina_clonar.sql).
class PaginasController extends Controller
{
    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'REQUIERE_NOMBRE' => 'Debe ingresar el nombre de la página.',
        'NOMBRE_LARGO' => 'El nombre no puede tener más de 80 caracteres.',
        'PAGINA_DUPLICADA' => 'Ya existe una página con ese nombre (si está restaurando, cambie primero el nombre de la otra página).',
        'ux_paginas_nombre' => 'Ya existe una página con ese nombre.',
        'DESCRIPCION_LARGA' => 'La descripción no puede tener más de 250 caracteres.',
        'REQUIERE_RUTA' => 'Debe ingresar la ruta de la página.',
        'RUTA_LARGA' => 'La ruta no puede tener más de 60 caracteres.',
        'RUTA_INVALIDA' => 'La ruta solo puede tener letras minúsculas, números y guiones (sin espacios, tildes ni barras). Ejemplo: ofertas-del-mes.',
        'RUTA_RESERVADA' => 'Esa ruta la usa una pantalla fija de la tienda (login, carrito, cuenta, producto...). Elija otra.',
        'RUTA_DUPLICADA' => 'Ya existe una página con esa ruta (si está restaurando, cambie primero la ruta de la otra página).',
        'ux_paginas_ruta' => 'Ya existe una página con esa ruta.',
        'VALOR_INVALIDO' => 'El valor de "Activo" no es válido.',
        'PAGINA_NO_EXISTE' => 'La página no existe o está en la papelera.',
        'PAPELERA_PAGINA_ACTIVA' => 'No se puede eliminar porque está activa: inactívela primero.',
        'PAPELERA_PAGINA_EN_USO' => 'No se puede eliminar porque es el destino de un enlace en una sección de otra página: cambie ese enlace primero.',
        'PAGINA_NO_EN_PAPELERA' => 'La página no está en la papelera (puede que otro usuario ya la restauró).',
        'SECCIONES_NO_COINCIDEN' => 'Las secciones elegidas para clonar no son de la página de origen. Vuelva a cargarla.',
    ];

    // Mensajes de la validación del formulario (crear, editar y clonar).
    private const MENSAJES_FORMULARIO = [
        'pag_nombre.required' => 'Debe ingresar el nombre de la página.',
        'pag_nombre.max' => 'El nombre no puede tener más de 80 caracteres.',
        'pag_descripcion.max' => 'La descripción no puede tener más de 250 caracteres.',
        'pag_ruta.required' => 'Debe ingresar la ruta de la página.',
        'pag_ruta.max' => 'La ruta no puede tener más de 60 caracteres.',
        'pag_activo.boolean' => 'El valor de "Activo" no es válido.',
        'secciones.array' => 'La lista de secciones no es válida.',
        'secciones.*.integer' => 'Una de las secciones elegidas no es válida.',
        'secciones.*.min' => 'Una de las secciones elegidas no es válida.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'pag_nombre',
        'pag_descripcion',
        'pag_ruta',
        'pag_activo',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Páginas (fuera de la papelera), paginadas -> ecommerce.fn_pagina_listar_paginacion. GET ?pagina=&tamanio=&busqueda=
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_pagina_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Páginas listadas con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las páginas', $th->getMessage()));
        }
    }

    // Una página con sus secciones en orden (también de la papelera), para verla, editarla o clonarla.
    public function ver(int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_pagina_buscar(?) AS pagina', [$id]);

            if (!$resultado || $resultado->pagina === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La página no existe.', null));
            }

            $secciones = DB::select('SELECT * FROM ecommerce.fn_pagina_secciones(?)', [$id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página cargada con éxito', [
                'pagina' => json_decode($resultado->pagina),
                'secciones' => $secciones,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página', $th->getMessage()));
        }
    }

    // Crea una página sin secciones -> ecommerce.fn_pagina_add. Body: { pag_nombre, pag_ruta, pag_descripcion?, pag_activo? }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pag_nombre' => 'required|string|max:80',
            'pag_descripcion' => 'nullable|string|max:250',
            'pag_ruta' => 'required|string|max:60',
            'pag_activo' => 'nullable|boolean',
        ], self::MENSAJES_FORMULARIO);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);

        try {
            // El bloque 'auditoria' va dentro del jsonb: la función lo usa para la auditoría forense y para el usu_id.
            $resultado = DB::selectOne('SELECT ecommerce.fn_pagina_add(?::jsonb) AS pag_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página creada con éxito', ['pag_id' => $resultado->pag_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la página');
        }
    }

    // Modifica la página -> ecommerce.fn_pagina_edit. pag_descripcion, pag_ruta y pag_activo son opcionales: si no vienen,
    // conservan su valor. Body: { pag_nombre, pag_ruta?, pag_descripcion?, pag_activo? }
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'pag_nombre' => 'required|string|max:80',
            'pag_descripcion' => 'nullable|string|max:250',
            'pag_ruta' => 'sometimes|required|string|max:60',
            'pag_activo' => 'sometimes|boolean',
        ], self::MENSAJES_FORMULARIO);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);
        $payload['pag_id'] = $id;

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_pagina_edit(?::jsonb) AS pag_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página modificada con éxito', ['pag_id' => $resultado->pag_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar la página');
        }
    }

    // Crea una página nueva a partir de otra, con copia de sus secciones -> ecommerce.fn_pagina_clonar. La descripción y
    // "activo", si no vienen, se copian del origen. secciones = las que el usuario dejó, en el orden en que quedarán
    // ([] = sin secciones); si no viene, se clonan todas.
    // Body: { pag_nombre, pag_ruta, pag_descripcion?, pag_activo?, secciones?: [sec_id, ...] }
    public function clonar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'pag_nombre' => 'required|string|max:80',
            'pag_descripcion' => 'nullable|string|max:250',
            'pag_ruta' => 'required|string|max:60',
            'pag_activo' => 'sometimes|boolean',
            'secciones' => 'sometimes|array',
            'secciones.*' => 'required|integer|min:1',
        ], self::MENSAJES_FORMULARIO);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $request->only(array_merge(self::CAMPOS_PAYLOAD, ['secciones'])) + ['pag_id_origen' => $id];

        try {
            // En su propio bloque (como las altas de SeccionesController): si falla, se deshace antes de armar el mensaje.
            $resultado = DB::transaction(fn() => DB::selectOne('SELECT ecommerce.fn_pagina_clonar(?::jsonb) AS pag_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]));

            return response()->json(RespuestaApi::returnResultado('success', 'Página clonada con éxito', ['pag_id' => $resultado->pag_id]));
        } catch (QueryException $e) {
            // Al copiar, cada sección se revalida: sus errores (contenido que ya no existe...) los explica SeccionesController.
            return SeccionesController::errorDeSeccion($e) ?? $this->errorTraducido($e, 'Error al clonar la página');
        }
    }

    // A la papelera: borrado lógico -> ecommerce.fn_pagina_papelera. No se puede vaciar.
    public function eliminar(Request $request, int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_pagina_papelera(?::jsonb) AS pag_id', [json_encode(['pag_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página enviada a la papelera', ['pag_id' => $resultado->pag_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar la página');
        }
    }

    // Vuelve de la papelera tal como estaba -> ecommerce.fn_pagina_restaurar.
    public function restaurar(Request $request, int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_pagina_restaurar(?::jsonb) AS pag_id', [json_encode(['pag_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página restaurada con éxito', ['pag_id' => $resultado->pag_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al restaurar la página');
        }
    }

    // Páginas en la papelera -> ecommerce.fn_pagina_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_pagina_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

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

    // Auditoría de UNA página (también de las de la papelera): resumen (quién la creó / última modificación) + eventos
    // paginados de auditoria.logs_cambios (incluye los cambios de orden de sus secciones). GET ?pag_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $pagId = (int) $request->query('pag_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($pagId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Página no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_pagina_auditoria_resumen(?)', [$pagId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_pagina_auditoria_listar_paginacion(?, ?, ?, ?)', [$pagId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la página', $th->getMessage()));
        }
    }

    // errorInfo[2] trae solo el mensaje de PG (getMessage() incluye el SQL con el payload).
    private function errorTraducido(QueryException $e, string $porDefecto)
    {
        $texto = $e->errorInfo[2] ?? $e->getMessage();

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($texto, $codigo) !== false) {
                // "En uso": la base manda en el DETAIL dónde ("Página" → "Sección"); se agrega al mensaje.
                if (str_ends_with($codigo, '_EN_USO') && preg_match('/DETAIL:\s*(.+)/', $texto, $m)) {
                    $mensaje .= ' Lo usa: ' . trim($m[1]) . '.';
                }

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
