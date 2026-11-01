<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\api\ApiEventos;
use App\Http\Resources\api\ApiPostmanHelper;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 30-oct F2: colecciones profesionales del API Client: carpetas anidadas, reordenar, mover y duplicar requests, duplicar
// colecciones e importar/exportar (Postman v2.1 o el formato nativo). Misma convención que ApiClientController: las
// funciones crm.fn_api_* validan, verifican el permiso y auditan; aquí se arma el jsonb, se traduce el código que levanta y
// se avisa por websockets (ApiEventos). La conversión Postman ↔ árbol normalizado vive en ApiPostmanHelper.
class ApiColeccionController extends Controller
{
    private const CAMPOS_CARPETA_CREAR = ['api_colecciones_id', 'padre_id', 'nombre', 'descripcion', 'orden'];
    private const CAMPOS_CARPETA_EDITAR = ['nombre', 'descripcion', 'padre_id', 'orden'];
    private const CAMPOS_MOVER = ['api_colecciones_id', 'api_carpetas_id', 'orden'];
    private const CAMPOS_ITEM_ORDEN = ['tipo', 'id', 'padre_id', 'orden'];
    private const MODOS_IMPORTAR = ['nueva', 'encima'];

    // Mensaje amigable para cada código que levantan las funciones crm.fn_api_* (F0 + F4 + los de la fase 2).
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
    // CARPETAS
    // ==============================

    // {api_colecciones_id, padre_id?, nombre, descripcion?, orden?} → ApiCarpeta (fn_api_carpeta_json). Editor o dueño.
    public function addCarpeta(Request $request)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_CARPETA_CREAR);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_carpeta_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $carpeta = $this->carpetaJson((int) $resultado->id);

            $log->logInfo(ApiColeccionController::class, 'Se creó la carpeta ID: ' . $resultado->id);
            ApiEventos::coleccion($this->entero($carpeta['api_colecciones_id'] ?? null), 'requests', $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta creada con éxito', $carpeta));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al crear la carpeta');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al crear la carpeta', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear la carpeta', $e->getMessage()));
        }
    }

    // Parcial: {nombre?, descripcion?, padre_id? (null = raíz), orden?}. padre_id dentro de sí misma → CICLO_CARPETAS.
    public function editCarpeta(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_CARPETA_EDITAR);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_carpeta_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $carpeta = $this->carpetaJson((int) $id);

            $log->logInfo(ApiColeccionController::class, 'Se actualizó la carpeta ID: ' . $id);
            ApiEventos::coleccion($this->entero($carpeta['api_colecciones_id'] ?? null), 'requests', $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta actualizada con éxito', $carpeta));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar la carpeta');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al actualizar la carpeta ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar la carpeta', $e->getMessage()));
        }
    }

    // Borra la carpeta con sus subcarpetas y requests (el historial de esos requests queda, desvinculado). → {id, api_colecciones_id}
    public function deleteCarpeta(Request $request, $id)
    {
        $log = new Funciones();
        try {
            // Se lee antes para devolver la colección a la que pertenecía (después ya no existe).
            $carpeta = $this->carpetaJson((int) $id);

            DB::selectOne('SELECT crm.fn_api_carpeta_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);
            $coleccionId = $this->entero($carpeta['api_colecciones_id'] ?? null);

            $log->logInfo(ApiColeccionController::class, 'Se eliminó la carpeta ID: ' . $id);
            ApiEventos::coleccion($coleccionId, 'requests', $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta eliminada con éxito', [
                'id' => (int) $id,
                'api_colecciones_id' => $coleccionId,
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar la carpeta');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al eliminar la carpeta ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la carpeta', $e->getMessage()));
        }
    }

    // ==============================
    // REORDENAR, MOVER, DUPLICAR
    // ==============================

    // {items: [{tipo: 'carpeta'|'request', id, padre_id (carpeta destino o null), orden}]} en un solo envío → ApiColeccion.
    public function reordenar(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $datos = $this->cuerpo($request, ['items']);
            $items = [];
            foreach (is_array($datos['items'] ?? null) ? $datos['items'] : [] as $item) {
                if (is_array($item)) {
                    $items[] = array_intersect_key($item, array_flip(self::CAMPOS_ITEM_ORDEN));
                }
            }

            DB::selectOne('SELECT crm.fn_api_coleccion_reordenar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'items' => $items,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ], JSON_UNESCAPED_UNICODE)]);

            $log->logInfo(ApiColeccionController::class, 'Se reordenó la colección ID: ' . $id . ' (' . count($items) . ' elementos)');
            ApiEventos::coleccion((int) $id, 'requests', $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', $this->buscarColeccion((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al reordenar la colección');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al reordenar la colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al reordenar la colección', $e->getMessage()));
        }
    }

    // {api_colecciones_id?, api_carpetas_id? (null = raíz), orden?} → fn_api_request_modificar sin updated_at (mover no
    // compite con la edición). Avisa a la colección destino y, si cambió, también a la de origen.
    public function moverRequest(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $antes = $this->buscarRequest((int) $id);
            $payload = $this->cuerpo($request, self::CAMPOS_MOVER);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_request_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $detalle = $this->buscarRequest((int) $id);

            $log->logInfo(ApiColeccionController::class, 'Se movió el request ID: ' . $id);

            $origen = $this->usuarioId();
            $coleccionAntes = $this->entero($antes['api_colecciones_id'] ?? null);
            $coleccionDespues = $this->entero($detalle['api_colecciones_id'] ?? null);
            ApiEventos::coleccion($coleccionDespues, 'requests', $origen, (int) $id);
            if ($coleccionAntes && $coleccionAntes !== $coleccionDespues) {
                ApiEventos::coleccion($coleccionAntes, 'requests', $origen, (int) $id);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Request movido con éxito', $detalle));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al mover el request');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al mover el request ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al mover el request', $e->getMessage()));
        }
    }

    // {nombre?} (por defecto "<nombre> (copia)") → ApiRequestDetalle del nuevo, en la misma carpeta.
    public function duplicarRequest(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, ['nombre']);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_request_duplicar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $detalle = $this->buscarRequest((int) $resultado->id);

            $log->logInfo(ApiColeccionController::class, 'Se duplicó el request ID: ' . $id . ' → ' . $resultado->id);
            ApiEventos::coleccion($this->entero($detalle['api_colecciones_id'] ?? null), 'requests', $this->usuarioId(), (int) $resultado->id);

            return response()->json(RespuestaApi::returnResultado('success', 'Request duplicado con éxito', $detalle));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al duplicar el request');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al duplicar el request ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al duplicar el request', $e->getMessage()));
        }
    }

    // {nombre?} → ApiColeccion nueva (copia profunda: carpetas, requests, auth, headers y variables) del usuario que duplica.
    public function duplicarColeccion(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, ['nombre']);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_coleccion_duplicar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $coleccion = $this->buscarColeccion((int) $resultado->id);

            $log->logInfo(ApiColeccionController::class, 'Se duplicó la colección ID: ' . $id . ' → ' . $resultado->id);
            ApiEventos::coleccion((int) $resultado->id, 'requests', $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Colección duplicada con éxito', $coleccion));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al duplicar la colección');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al duplicar la colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al duplicar la colección', $e->getMessage()));
        }
    }

    // ==============================
    // IMPORTAR / EXPORTAR
    // ==============================

    // {modo: 'nueva'|'encima', id? (encima, solo dueño), nombre? (nueva), contenido: <JSON Postman v2.1 | exportación nativa>}
    // (contenido como objeto o como texto). → {coleccion: ApiColeccion, resumen: {carpetas, requests, ejemplos, no_soportado}}
    public function importar(Request $request)
    {
        $log = new Funciones();
        try {
            $datos = $this->cuerpo($request, ['modo', 'id', 'nombre', 'contenido']);
            $modo = strtolower(trim((string) ($datos['modo'] ?? ''))) ?: 'nueva';
            if (!in_array($modo, self::MODOS_IMPORTAR, true)) {
                return $this->error('FORMATO_INVALIDO');
            }
            $id = $this->entero($datos['id'] ?? null);
            if ($modo === 'encima' && !$id) {
                return $this->error('REQUIERE_COLECCION');
            }

            $contenido = $datos['contenido'] ?? null;
            if (is_string($contenido)) {
                $contenido = json_decode($contenido, true);
            }
            if (!is_array($contenido)) {
                return $this->error('IMPORTACION_INVALIDA');
            }
            if (ApiPostmanHelper::esPostman($contenido)) {
                $normalizado = ApiPostmanHelper::desdeV21($contenido);
            } elseif (ApiPostmanHelper::esNativo($contenido)) {
                $normalizado = ApiPostmanHelper::desdeNativo($contenido);
            } else {
                return $this->error('IMPORTACION_INVALIDA');
            }
            $resumen = ApiPostmanHelper::resumen($normalizado);
            unset($normalizado['no_soportado']);

            $nombre = trim((string) ($datos['nombre'] ?? ''));
            $normalizado['nombre'] = $nombre !== '' ? $nombre : (trim((string) ($normalizado['nombre'] ?? '')) ?: 'Colección importada');
            $normalizado['modo'] = $modo;
            if ($modo === 'encima') {
                $normalizado['id'] = $id;
            }
            $normalizado['auditoria'] = $this->contextoAuditoriaForense($request);
            $json = json_encode($normalizado, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                return $this->error('IMPORTACION_INVALIDA');
            }

            $resultado = DB::selectOne('SELECT crm.fn_api_coleccion_importar(?::jsonb) AS id', [$json]);
            $coleccionId = (int) $resultado->id;

            $log->logInfo(ApiColeccionController::class, 'Se importó la colección ID: ' . $coleccionId . ' (' . $modo . '): ' . $resumen['carpetas'] . ' carpetas, '
                . $resumen['requests'] . ' requests, ' . $resumen['ejemplos'] . ' ejemplos');
            if ($modo === 'encima') {
                ApiEventos::coleccion($coleccionId, 'requests', $this->usuarioId());
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Colección importada con éxito', [
                'coleccion' => $this->buscarColeccion($coleccionId),
                'resumen' => $resumen,
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al importar la colección');
        } catch (Exception $e) {
            $log->logError(ApiColeccionController::class, 'Error al importar la colección', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al importar la colección', $e->getMessage()));
        }
    }

    // ?credenciales=0|1 (1 solo para el dueño) &formato=postman|nativo. Descarga JSON con Content-Disposition; con error
    // responde la envoltura con HTTP 422 para que el navegador no la baje como archivo (igual que exportarEntorno).
    public function exportar(Request $request, $id)
    {
        try {
            $credenciales = in_array((string) $request->query('credenciales', '0'), ['1', 'true'], true);
            $formato = strtolower((string) $request->query('formato', 'postman')) === 'nativo' ? 'nativo' : 'postman';

            // El booleano va como texto: PDO manda false como '' y '::boolean' lo rechaza.
            $exportado = $this->leerJsonb('SELECT crm.fn_api_coleccion_exportar(?::bigint, ?::bigint, ?::boolean) AS datos',
                [(int) $id, $this->usuarioId(), $credenciales ? 'true' : 'false']);
            if (!is_array($exportado)) {
                return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_ERROR['COLECCION_NO_EXISTE'], null), 422);
            }

            $nombre = (string) ($exportado['coleccion']['nombre'] ?? '');
            $salida = $formato === 'nativo' ? $exportado : ApiPostmanHelper::aV21($exportado);
            $archivo = (Str::slug($nombre) ?: (string) (int) $id) . ($formato === 'nativo' ? '.api-client.json' : '.postman_collection.json');

            return response()->json($salida, 200, [
                'Content-Disposition' => 'attachment; filename="' . $archivo . '"',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo exportar la colección')->setStatusCode(422);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo exportar la colección', $e->getMessage()), 422);
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
    // ConvertEmptyStringsToNull también limpian $request->json(): así los valores llegan tal cual.
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

    // ApiCarpeta plana (sin hijos). El permiso ya lo verificó la función de escritura que la precede.
    private function carpetaJson(int $id): ?array
    {
        return $this->leerJsonb('SELECT crm.fn_api_carpeta_json(?::bigint) AS datos', [$id]);
    }

    private function entero($valor): ?int
    {
        return $valor !== null && $valor !== '' && is_numeric($valor) ? (int) $valor : null;
    }

    // Error detectado aquí (antes de la base) con el mismo mensaje que daría la función.
    private function error(string $codigo)
    {
        return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_ERROR[$codigo] ?? $codigo, null));
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico (como ApiClientController).
    private function respuestaError(QueryException $e, string $generico)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        (new Funciones())->logError(ApiColeccionController::class, $generico, $e);

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
