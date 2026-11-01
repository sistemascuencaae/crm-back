<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\api\ApiEventos; // 30-oct F4: avisos por websockets
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 30-oct: CRUD del módulo API Client (colecciones, requests, historial y variables) por funciones crm.fn_api_*: la
// validación, el permiso y la auditoría forense viven en la función; aquí se arma el jsonb y se traduce el código que
// levanta. El usuario dueño/consultante va SIEMPRE en auditoria.usuario_id (nunca lo manda el front).
class ApiClientController extends Controller
{
    // QUITADO 30-oct F2: sin auth/headers/variables de la colección ni carpeta del request (reactivar = descomentar)
    // private const CAMPOS_COLECCION = ['nombre', 'descripcion', 'color', 'orden'];
    // private const CAMPOS_REQUEST = ['api_colecciones_id', 'nombre', 'metodo', 'url', 'params', 'headers', 'body_tipo', 'body_subtipo', 'body',
    //     'auth_tipo', 'auth_data', 'opciones', 'descripcion', 'updated_at'];
    // 30-oct F2: la colección también guarda auth_tipo/auth_data (heredable), headers y variables; el request su carpeta
    private const CAMPOS_COLECCION = ['nombre', 'descripcion', 'color', 'orden', 'auth_tipo', 'auth_data', 'headers', 'variables'];
    private const CAMPOS_REQUEST = ['api_colecciones_id', 'api_carpetas_id', 'nombre', 'metodo', 'url', 'params', 'headers', 'body_tipo', 'body_subtipo', 'body',
        'auth_tipo', 'auth_data', 'opciones', 'descripcion', 'updated_at'];
    // QUITADO 31-oct (fase 6): sin el origen de la variable (reactivar = descomentar)
    // private const CAMPOS_VARIABLE = ['nombre', 'valor', 'descripcion', 'estado'];
    private const CAMPOS_VARIABLE = ['nombre', 'valor', 'descripcion', 'estado', 'origen']; // 31-oct (fase 6): origen = {request_id, tipo, ruta} (valor desde la respuesta de otro request)

    // Mensaje amigable para cada código que levantan las funciones crm.fn_api_*.
    private const MENSAJES_ERROR = [
        'SIN_PERMISO' => 'No tienes permiso sobre esta colección.',
        // 31-oct F6: variables desde la respuesta. Van aquí, antes de REQUEST_NO_EXISTE: respuestaError busca por subcadena en orden.
        'ORIGEN_SIN_REQUEST' => 'El request de origen no existe o no tienes acceso a él.',
        'ORIGEN_TIPO_INVALIDO' => 'El origen debe ser body, header o cookie.',
        'ORIGEN_RUTA_INVALIDA' => 'La ruta del origen no es válida (ejemplo: $.data.token).',
        'COLECCION_NO_EXISTE' => 'La colección no existe.',
        'REQUEST_NO_EXISTE' => 'El request no existe.',
        'HISTORIAL_NO_EXISTE' => 'El registro del historial no existe.',
        'VARIABLE_NO_EXISTE' => 'La variable no existe.',
        'REQUIERE_NOMBRE' => 'El nombre es obligatorio.',
        'REQUIERE_URL' => 'La URL es obligatoria.',
        'REQUIERE_COLECCION' => 'Debe indicar la colección.',
        'METODO_INVALIDO' => 'El método HTTP no es válido.',
        'BODY_TIPO_INVALIDO' => 'El tipo de body no es válido.',
        'AUTH_TIPO_INVALIDO' => 'El tipo de autenticación no es válido.',
        'COLOR_INVALIDO' => 'El color debe ser hexadecimal, por ejemplo #0d6efd.',
        'VARIABLE_DUPLICADA' => 'Ya existe una variable con ese nombre.',
        'VARIABLE_NOMBRE_INVALIDO' => 'El nombre de la variable solo admite letras, números, guion, guion bajo y punto.',
        'REQUIERE_VALOR' => 'El valor es obligatorio.',
        'CONFLICTO_EDICION' => 'Otro usuario modificó este request. Recarga antes de guardar.',
        'FORMATO_INVALIDO' => 'El formato de los datos no es válido.',
        'ORDEN_INVALIDO' => 'El orden debe ser un número.',
        'SIN_USUARIO' => 'No se pudo identificar al usuario.',
        // 30-oct F4
        'REQUIERE_USUARIO' => 'Debe elegir al menos un usuario.',
        'DUENO_NO_SE_AGREGA' => 'El dueño ya tiene acceso a su colección.',
        'USUARIO_NO_EXISTE' => 'El usuario no existe o está inactivo.',
        'USUARIO_NO_AGREGADO' => 'Ese usuario no está en la colección.',
        'ENTORNO_NO_EXISTE' => 'El entorno no existe.',
        'ENTORNO_DUPLICADO' => 'Ya existe un entorno con ese nombre.',
        'VARIABLE_NO_SECRETA' => 'La variable no es secreta.',
        // 30-oct F2
        'CARPETA_NO_EXISTE' => 'La carpeta no existe.',
        'CARPETA_DE_OTRA_COLECCION' => 'La carpeta pertenece a otra colección.',
        'CICLO_CARPETAS' => 'Una carpeta no puede ir dentro de sí misma.',
        'ITEM_DE_OTRA_COLECCION' => 'Uno de los elementos no pertenece a la colección.',
        'IMPORTACION_INVALIDA' => 'El archivo no tiene el formato esperado.',
        'EJEMPLO_NO_EXISTE' => 'El ejemplo no existe.',
    ];

    // ==============================
    // COLECCIONES
    // ==============================

    public function listColecciones(Request $request)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_coleccion_listar(?::bigint) AS datos', [$this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $datos ?? []));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron listar las colecciones');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las colecciones', $e->getMessage()));
        }
    }

    public function getColeccion(Request $request, $id)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $this->buscarColeccion((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar la colección');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la colección', $e->getMessage()));
        }
    }

    public function addColeccion(Request $request)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_COLECCION);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_coleccion_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiClientController::class, 'Se creó la colección ID: ' . $resultado->id);

            return response()->json(RespuestaApi::returnResultado('success', 'Colección creada con éxito', $this->buscarColeccion((int) $resultado->id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al crear la colección');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al crear la colección', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear la colección', $e->getMessage()));
        }
    }

    public function editColeccion(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_COLECCION);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_coleccion_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiClientController::class, 'Se actualizó la colección ID: ' . $id);
            ApiEventos::coleccion((int) $id, 'coleccion', $this->usuarioId()); // 30-oct F4

            return response()->json(RespuestaApi::returnResultado('success', 'Colección actualizada con éxito', $this->buscarColeccion((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar la colección');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al actualizar la colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar la colección', $e->getMessage()));
        }
    }

    public function deleteColeccion(Request $request, $id)
    {
        $log = new Funciones();
        try {
            // 30-oct F4: los usuarios agregados se leen ANTES de borrar (después ya no existen) para avisarles. Solo si es
            // el dueño (fn_api_coleccion_permiso no levanta error): si no lo es, lo dirá la función de eliminar.
            $usuariosAgregados = [];
            $permiso = DB::selectOne('SELECT crm.fn_api_coleccion_permiso(?::bigint, ?::bigint) AS rol', [(int) $id, $this->usuarioId()]);
            if ($permiso && $permiso->rol === 'dueno') {
                $usuariosAgregados = $this->leerJsonb('SELECT crm.fn_api_coleccion_usuarios_listar(?::bigint, ?::bigint) AS datos', [(int) $id, $this->usuarioId()]) ?? [];
            }

            DB::selectOne('SELECT crm.fn_api_coleccion_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            $log->logInfo(ApiClientController::class, 'Se eliminó la colección ID: ' . $id);

            // 30-oct F4: aviso al canal de la colección y a cada usuario agregado
            $origen = $this->usuarioId();
            ApiEventos::coleccion((int) $id, 'eliminada', $origen);
            foreach ($usuariosAgregados as $u) {
                ApiEventos::usuario((int) ($u['users_id'] ?? 0), (int) $id, 'eliminada', $origen);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Colección eliminada con éxito', ['id' => (int) $id]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar la colección');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al eliminar la colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la colección', $e->getMessage()));
        }
    }

    // Los requests resumidos (id, nombre, metodo, url, orden, updated_at) de una colección que el usuario puede ver.
    public function listRequests(Request $request, $id)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_request_listar(?::bigint, ?::bigint) AS datos', [(int) $id, $this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $datos ?? []));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron listar los requests');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los requests', $e->getMessage()));
        }
    }

    // ==============================
    // REQUESTS
    // ==============================

    public function getRequest(Request $request, $id)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $this->buscarRequest((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar el request');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el request', $e->getMessage()));
        }
    }

    public function addRequest(Request $request)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_REQUEST);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_request_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiClientController::class, 'Se guardó el request ID: ' . $resultado->id);

            $detalle = $this->buscarRequest((int) $resultado->id);
            ApiEventos::coleccion((int) ($detalle['api_colecciones_id'] ?? 0), 'requests', $this->usuarioId(), (int) $resultado->id); // 30-oct F4

            return response()->json(RespuestaApi::returnResultado('success', 'Request guardado con éxito', $detalle));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar el request');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al guardar el request', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el request', $e->getMessage()));
        }
    }

    // updated_at (snapshot) viaja dentro del payload: si cambió en la base, la función levanta CONFLICTO_EDICION.
    public function editRequest(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_REQUEST);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_request_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiClientController::class, 'Se actualizó el request ID: ' . $id);

            $detalle = $this->buscarRequest((int) $id);
            ApiEventos::coleccion((int) ($detalle['api_colecciones_id'] ?? 0), 'requests', $this->usuarioId(), (int) $id); // 30-oct F4

            return response()->json(RespuestaApi::returnResultado('success', 'Request actualizado con éxito', $detalle));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar el request');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al actualizar el request ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el request', $e->getMessage()));
        }
    }

    public function deleteRequest(Request $request, $id)
    {
        $log = new Funciones();
        try {
            // Se busca antes para devolver la colección a la que pertenecía (el front la recarga).
            $detalle = $this->buscarRequest((int) $id);

            DB::selectOne('SELECT crm.fn_api_request_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            $log->logInfo(ApiClientController::class, 'Se eliminó el request ID: ' . $id);
            ApiEventos::coleccion((int) ($detalle['api_colecciones_id'] ?? 0), 'requests', $this->usuarioId(), (int) $id); // 30-oct F4

            return response()->json(RespuestaApi::returnResultado('success', 'Request eliminado con éxito', [
                'id' => (int) $id,
                'api_colecciones_id' => $detalle['api_colecciones_id'] ?? null,
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el request');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al eliminar el request ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el request', $e->getMessage()));
        }
    }

    // ==============================
    // HISTORIAL
    // ==============================

    // Paginado y con filtros (texto, método, rango de status, colección y fechas). Responde {registros, total, pagina, tamanio}.
    public function listHistorial(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 50), 1), 200);

            $registros = DB::select('SELECT * FROM crm.fn_api_historial_listar_paginacion(?::jsonb)', [json_encode([
                'usuario_id' => $this->usuarioId(),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
                'texto' => $this->textoONulo($request->query('texto')),
                'metodo' => $this->textoONulo($request->query('metodo')),
                'status_desde' => $this->enteroONulo($request->query('status_desde')),
                'status_hasta' => $this->enteroONulo($request->query('status_hasta')),
                'coleccion_id' => $this->enteroONulo($request->query('coleccion_id')),
                'fecha_desde' => $this->textoONulo($request->query('fecha_desde')),
                'fecha_hasta' => $this->textoONulo($request->query('fecha_hasta')),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo listar el historial');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo listar el historial', $e->getMessage()));
        }
    }

    // El detalle con respuesta_body y respuesta_headers (auth_data llega enmascarado desde la función).
    public function getHistorial(Request $request, $id)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_historial_buscar(?::bigint, ?::bigint) AS datos', [(int) $id, $this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $datos));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar el registro del historial');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el registro del historial', $e->getMessage()));
        }
    }

    public function deleteHistorial(Request $request, $id)
    {
        try {
            DB::selectOne('SELECT crm.fn_api_historial_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Registro eliminado con éxito', ['id' => (int) $id]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el registro del historial');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el registro del historial', $e->getMessage()));
        }
    }

    // Borra el historial del usuario; con ?antes_de=YYYY-MM-DD solo lo anterior a esa fecha.
    public function clearHistorial(Request $request)
    {
        $log = new Funciones();
        try {
            $resultado = DB::selectOne('SELECT crm.fn_api_historial_limpiar(?::jsonb) AS eliminados', [json_encode([
                'antes_de' => $this->textoONulo($request->query('antes_de')),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            $log->logInfo(ApiClientController::class, 'Se limpió el historial: ' . (int) $resultado->eliminados . ' registros');

            return response()->json(RespuestaApi::returnResultado('success', 'Historial limpiado con éxito', ['eliminados' => (int) $resultado->eliminados]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al limpiar el historial');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al limpiar el historial', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al limpiar el historial', $e->getMessage()));
        }
    }

    // ==============================
    // VARIABLES (globales del usuario)
    // ==============================

    public function listVariables(Request $request)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_variable_listar(?::bigint) AS datos', [$this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $datos ?? []));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron listar las variables');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las variables', $e->getMessage()));
        }
    }

    public function addVariable(Request $request)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_VARIABLE);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_variable_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiClientController::class, 'Se creó la variable ID: ' . $resultado->id);

            return response()->json(RespuestaApi::returnResultado('success', 'Variable creada con éxito', $this->buscarVariable((int) $resultado->id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al crear la variable');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al crear la variable', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear la variable', $e->getMessage()));
        }
    }

    public function editVariable(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_VARIABLE);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_variable_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiClientController::class, 'Se actualizó la variable ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Variable actualizada con éxito', $this->buscarVariable((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar la variable');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al actualizar la variable ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar la variable', $e->getMessage()));
        }
    }

    public function deleteVariable(Request $request, $id)
    {
        $log = new Funciones();
        try {
            DB::selectOne('SELECT crm.fn_api_variable_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            $log->logInfo(ApiClientController::class, 'Se eliminó la variable ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Variable eliminada con éxito', ['id' => (int) $id]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar la variable');
        } catch (Exception $e) {
            $log->logError(ApiClientController::class, 'Error al eliminar la variable ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la variable', $e->getMessage()));
        }
    }

    // 31-oct (fase 6): GET variables/origen-probar?request_id=&tipo=&ruta= → vista previa de un origen (body|header|cookie de la
    // última respuesta exitosa de ese request hecha por el usuario). fn_api_variable_origen_probar normaliza el origen
    // (ORIGEN_SIN_REQUEST, ORIGEN_TIPO_INVALIDO, ORIGEN_RUTA_INVALIDA) y devuelve {request_id, request_nombre, metodo, coleccion_id,
    // coleccion_nombre, tipo, ruta, valor (en claro), encontrado, respuesta_fecha}; quien muestra enmascara si la variable es secreta.
    public function probarOrigenVariable(Request $request)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_variable_origen_probar(?::jsonb) AS datos', [json_encode([
                'origen' => [
                    'request_id' => (int) $request->query('request_id'),
                    'tipo' => (string) $request->query('tipo', ''),
                    'ruta' => (string) $request->query('ruta', ''),
                ],
                'auditoria' => $this->contextoAuditoriaForense($request),
            ], JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se consultó con éxito', $datos));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo probar el origen');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo probar el origen', $e->getMessage()));
        }
    }

    // ==============================
    // AYUDANTES
    // ==============================

    private function usuarioId(): ?int
    {
        $u = auth('api')->user();

        return $u ? (int) $u->id : null;
    }

    // El cuerpo filtrado a los campos permitidos. Con JSON se lee crudo (getContent) porque TrimStrings y
    // ConvertEmptyStringsToNull también limpian $request->json(): así body y los valores vacíos llegan tal cual.
    private function cuerpo(Request $request, array $campos): array
    {
        $datos = $request->isJson() ? json_decode($request->getContent(), true) : $request->all();

        return array_intersect_key(is_array($datos) ? $datos : [], array_flip($campos));
    }

    private function leerJsonb(string $sql, array $parametros): ?array
    {
        $fila = DB::selectOne($sql, $parametros);

        return $fila && $fila->datos !== null ? json_decode($fila->datos, true) : null;
    }

    private function buscarColeccion(int $id): ?array
    {
        return $this->leerJsonb('SELECT crm.fn_api_coleccion_buscar(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]);
    }

    private function buscarRequest(int $id): ?array
    {
        return $this->leerJsonb('SELECT crm.fn_api_request_buscar(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]);
    }

    private function buscarVariable(int $id): ?array
    {
        return $this->leerJsonb('SELECT crm.fn_api_variable_buscar(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]);
    }

    private function textoONulo($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function enteroONulo($valor): ?int
    {
        return $valor === null || $valor === '' || !is_numeric($valor) ? null : (int) $valor;
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico (como AgenciaController).
    private function respuestaError(QueryException $e, string $generico)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        (new Funciones())->logError(ApiClientController::class, $generico, $e);

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios (módulo API-CLIENT).
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
