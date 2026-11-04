<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\api\ApiEventos;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 30-oct F3: ejemplos del API Client (respuestas guardadas por request, como los "examples" de Postman). Misma convención
// que ApiClientController: las funciones crm.fn_api_request_ejemplo_* validan, verifican el permiso (lector para leer,
// editor para escribir), quitan la auth del snapshot, enmascaran los headers sensibles y auditan; aquí se arma el jsonb, se
// traduce el código que levanta y se avisa por websockets (ApiEventos 'requests' con el request_id, como al editar el request).
class ApiEjemploController extends Controller
{
    private const CAMPOS_GUARDAR = ['nombre', 'request_snapshot', 'respuesta_status', 'respuesta_headers', 'respuesta_body', 'respuesta_content_type', 'respuesta_tiempo_ms'];
    private const CAMPOS_MODIFICAR = ['nombre', 'orden'];

    // Mensaje amigable para cada código que levantan las funciones crm.fn_api_* (los mismos de los otros controladores).
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
        'REQUIERE_USUARIO' => 'Debe elegir al menos un usuario.',
        'DUENO_NO_SE_AGREGA' => 'El dueño ya tiene acceso a su colección.',
        'USUARIO_NO_EXISTE' => 'El usuario no existe o está inactivo.',
        'USUARIO_NO_AGREGADO' => 'Ese usuario no está en la colección.',
        'ENTORNO_NO_EXISTE' => 'El entorno no existe.',
        'ENTORNO_DUPLICADO' => 'Ya existe un entorno con ese nombre.',
        'VARIABLE_NO_SECRETA' => 'La variable no es secreta.',
        'CARPETA_NO_EXISTE' => 'La carpeta no existe.',
        'CARPETA_DE_OTRA_COLECCION' => 'La carpeta pertenece a otra colección.',
        'CICLO_CARPETAS' => 'Una carpeta no puede ir dentro de sí misma.',
        'ITEM_DE_OTRA_COLECCION' => 'Uno de los elementos no pertenece a la colección.',
        'IMPORTACION_INVALIDA' => 'El archivo no tiene el formato esperado.',
        'EJEMPLO_NO_EXISTE' => 'El ejemplo no existe.',
    ];

    // GET requests/{id}/ejemplos → ApiRequestEjemplo[] (lector de la colección; en orden).
    public function listEjemplos(Request $request, $id)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_request_ejemplos_listar(?::bigint, ?::bigint) AS datos', [(int) $id, $this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $datos ?? []));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron listar los ejemplos');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los ejemplos', $e->getMessage()));
        }
    }

    // GET ejemplos/{id} → ApiRequestEjemplo (con request_snapshot y el cuerpo de la respuesta).
    public function getEjemplo(Request $request, $id)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $this->buscarEjemplo((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar el ejemplo');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el ejemplo', $e->getMessage()));
        }
    }

    // POST requests/{id}/ejemplos {nombre, request_snapshot, respuesta_status, respuesta_headers, respuesta_body,
    // respuesta_content_type, respuesta_tiempo_ms} → ApiRequestEjemplo. El cuerpo se recorta a API_CLIENT_HISTORIAL_MAX_KB
    // (max_kb; la función también lo hace) y el snapshot sale sin auth y con los headers sensibles enmascarados. Editor.
    public function addEjemplo(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $maxKb = $this->maxKb();
            $payload = $this->cuerpo($request, self::CAMPOS_GUARDAR);
            $payload['api_requests_id'] = (int) $id;
            $payload['max_kb'] = $maxKb;
            if (isset($payload['respuesta_body']) && is_string($payload['respuesta_body'])) {
                $payload['respuesta_body'] = $this->textoAcotado($payload['respuesta_body'], $maxKb * 1024);
            }
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_request_ejemplo_guardar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);
            $ejemplo = $this->buscarEjemplo((int) $resultado->id);

            $log->logInfo(ApiEjemploController::class, 'Se guardó el ejemplo ID: ' . $resultado->id . ' del request ' . $id);
            ApiEventos::coleccion($this->entero($ejemplo['api_colecciones_id'] ?? null), 'requests', $this->usuarioId(), (int) $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Ejemplo guardado con éxito', $ejemplo));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar el ejemplo');
        } catch (Exception $e) {
            $log->logError(ApiEjemploController::class, 'Error al guardar el ejemplo del request ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el ejemplo', $e->getMessage()));
        }
    }

    // PUT ejemplos/{id} {nombre} (también acepta orden) → ApiRequestEjemplo. Editor.
    public function editEjemplo(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_MODIFICAR);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_request_ejemplo_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $ejemplo = $this->buscarEjemplo((int) $id);

            $log->logInfo(ApiEjemploController::class, 'Se actualizó el ejemplo ID: ' . $id);
            ApiEventos::coleccion($this->entero($ejemplo['api_colecciones_id'] ?? null), 'requests', $this->usuarioId(), $this->entero($ejemplo['api_requests_id'] ?? null));

            return response()->json(RespuestaApi::returnResultado('success', 'Ejemplo actualizado con éxito', $ejemplo));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar el ejemplo');
        } catch (Exception $e) {
            $log->logError(ApiEjemploController::class, 'Error al actualizar el ejemplo ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el ejemplo', $e->getMessage()));
        }
    }

    // DELETE ejemplos/{id} → {id, api_requests_id}. Se lee antes de borrar para saber a qué request y colección avisar. Editor.
    public function deleteEjemplo(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $ejemplo = $this->buscarEjemplo((int) $id);

            DB::selectOne('SELECT crm.fn_api_request_ejemplo_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);
            $requestId = $this->entero($ejemplo['api_requests_id'] ?? null);

            $log->logInfo(ApiEjemploController::class, 'Se eliminó el ejemplo ID: ' . $id);
            ApiEventos::coleccion($this->entero($ejemplo['api_colecciones_id'] ?? null), 'requests', $this->usuarioId(), $requestId);

            return response()->json(RespuestaApi::returnResultado('success', 'Ejemplo eliminado con éxito', [
                'id' => (int) $id,
                'api_requests_id' => $requestId,
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el ejemplo');
        } catch (Exception $e) {
            $log->logError(ApiEjemploController::class, 'Error al eliminar el ejemplo ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el ejemplo', $e->getMessage()));
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
    // ConvertEmptyStringsToNull también limpian $request->json(): así el cuerpo de la respuesta llega tal cual.
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

    // Levanta EJEMPLO_NO_EXISTE o SIN_PERMISO (lector) si corresponde.
    private function buscarEjemplo(int $id): ?array
    {
        return $this->leerJsonb('SELECT crm.fn_api_request_ejemplo_buscar(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]);
    }

    // Mismo tope que el historial (KB del cuerpo guardado).
    private function maxKb(): int
    {
        return max(1, (int) env('API_CLIENT_HISTORIAL_MAX_KB', 256));
    }

    // Recorta a N bytes sin partir un carácter UTF-8 y sin bytes nulos (el jsonb no los acepta).
    private function textoAcotado(string $texto, int $maxBytes): string
    {
        if (strlen($texto) > $maxBytes) {
            $texto = mb_strcut($texto, 0, $maxBytes);
        }

        return str_replace("\0", '', $texto);
    }

    private function entero($valor): ?int
    {
        return $valor !== null && $valor !== '' && is_numeric($valor) ? (int) $valor : null;
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

        (new Funciones())->logError(ApiEjemploController::class, $generico, $e);

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
