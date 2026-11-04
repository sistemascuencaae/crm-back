<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\api\ApiEventos;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

// 30-oct: fase 4 del API Client: usuarios de la colección (compartir), actividad (auditoría forense por colección) y
// entornos con variables secretas por usuario. Misma convención que ApiClientController: las funciones crm.fn_api_*
// validan, verifican el permiso y auditan; aquí se arma el jsonb, se traduce el código y se avisa por websockets.
// Los valores secretos se cifran con Crypt ANTES de llegar a la base y solo se descifran para el usuario que los guardó.
class ApiColaboracionController extends Controller
{
    private const CAMPOS_ENTORNO_CREAR = ['nombre', 'descripcion', 'color', 'orden', 'api_colecciones_id'];
    private const CAMPOS_ENTORNO_EDITAR = ['nombre', 'descripcion', 'color', 'orden'];
    // QUITADO 31-oct (fase 6): sin el origen de la variable (reactivar = descomentar)
    // private const CAMPOS_VARIABLE = ['id', 'nombre', 'valor', 'secreta', 'estado', 'descripcion'];
    private const CAMPOS_VARIABLE = ['id', 'nombre', 'valor', 'secreta', 'estado', 'descripcion', 'origen']; // 31-oct (fase 6): origen = {request_id, tipo, ruta}
    private const PATRON_NOMBRE_VARIABLE = '/^[A-Za-z0-9_][A-Za-z0-9_.\-]*$/';
    private const MASCARA = '***';

    // Mensaje amigable para cada código que levantan las funciones crm.fn_api_* (los de F0 más los de la fase 4).
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
    ];

    // ==============================
    // USUARIOS DE LA COLECCIÓN
    // ==============================

    public function listUsuarios(Request $request, $id)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $this->usuariosColeccion((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron listar los usuarios de la colección');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los usuarios de la colección', $e->getMessage()));
        }
    }

    // {users_ids: number[]} (o users_id). Solo el dueño; los ya agregados se omiten. Devuelve {agregados, usuarios} y
    // avisa a la colección ('usuarios') y a cada usuario nuevo ('compartida').
    public function addUsuarios(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, ['users_ids', 'users_id']);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);
            $antes = array_map('intval', array_column($this->usuariosColeccion((int) $id), 'users_id'));

            $resultado = DB::selectOne('SELECT crm.fn_api_coleccion_usuario_agregar(?::jsonb) AS n', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $agregados = (int) $resultado->n;
            $usuarios = $this->usuariosColeccion((int) $id);

            $log->logInfo(ApiColaboracionController::class, 'Se agregaron ' . $agregados . ' usuarios a la colección ID: ' . $id);

            $origen = $this->usuarioId();
            $nuevos = array_diff(array_map('intval', array_column($usuarios, 'users_id')), $antes);
            if ($agregados > 0) {
                ApiEventos::coleccion((int) $id, 'usuarios', $origen);
                foreach ($nuevos as $usuarioNuevo) {
                    ApiEventos::usuario((int) $usuarioNuevo, (int) $id, 'compartida', $origen);
                }
            }

            return response()->json(RespuestaApi::returnResultado('success',
                $agregados > 0 ? 'Usuarios agregados con éxito' : 'Los usuarios elegidos ya estaban en la colección',
                ['agregados' => $agregados, 'usuarios' => $usuarios]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al agregar usuarios a la colección');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al agregar usuarios a la colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al agregar usuarios a la colección', $e->getMessage()));
        }
    }

    // El dueño quita a cualquiera; un usuario agregado se sale él mismo (uid = su id). Devuelve {usuarios} y avisa a la
    // colección ('usuarios') y al quitado ('eliminada').
    public function deleteUsuario(Request $request, $id, $uid)
    {
        $log = new Funciones();
        try {
            DB::selectOne('SELECT crm.fn_api_coleccion_usuario_quitar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'users_id' => (int) $uid,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            // Si el que se salió es el consultante ya no puede ver la lista: para él queda vacía (se consulta el permiso,
            // que no levanta error, en vez de listar a ciegas).
            $permiso = DB::selectOne('SELECT crm.fn_api_coleccion_permiso(?::bigint, ?::bigint) AS rol', [(int) $id, $this->usuarioId()]);
            $usuarios = $permiso && $permiso->rol !== null ? $this->usuariosColeccion((int) $id) : [];

            $log->logInfo(ApiColaboracionController::class, 'Se quitó al usuario ' . $uid . ' de la colección ID: ' . $id);

            $origen = $this->usuarioId();
            ApiEventos::coleccion((int) $id, 'usuarios', $origen);
            ApiEventos::usuario((int) $uid, (int) $id, 'eliminada', $origen);

            return response()->json(RespuestaApi::returnResultado('success', 'Usuario quitado con éxito', ['usuarios' => $usuarios]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al quitar el usuario de la colección');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al quitar al usuario ' . $uid . ' de la colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al quitar el usuario de la colección', $e->getMessage()));
        }
    }

    // ==============================
    // ACTIVIDAD (auditoría forense de la colección)
    // ==============================

    // ?pagina&tamanio&busqueda → {resumen, eventos, total, pagina, tamanio} (misma forma que la auditoría de anuncios).
    public function getAuditoria(Request $request, $id)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 200);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = $this->leerJsonb('SELECT crm.fn_api_coleccion_auditoria_resumen(?::bigint, ?::bigint) AS datos', [(int) $id, $this->usuarioId()]);
            $eventos = DB::select('SELECT * FROM crm.fn_api_coleccion_auditoria_listar_paginacion(?::bigint, ?::bigint, ?::integer, ?::integer, ?::text)',
                [(int) $id, $this->usuarioId(), $pagina, $tamanio, $busqueda]);
            foreach ($eventos as $evento) {
                // jsonb llega como texto: el front recibe objetos
                $evento->old_values = is_string($evento->old_values) ? json_decode($evento->old_values, true) : $evento->old_values;
                $evento->new_values = is_string($evento->new_values) ? json_decode($evento->new_values, true) : $evento->new_values;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Actividad cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar la actividad de la colección');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la actividad de la colección', $e->getMessage()));
        }
    }

    // ==============================
    // ENTORNOS
    // ==============================

    // Personales del usuario + los de las colecciones donde participa (las secretas llegan con valor NULL y mi_valor_definido).
    public function listEntornos(Request $request)
    {
        try {
            $datos = $this->leerJsonb('SELECT crm.fn_api_entorno_listar(?::bigint) AS datos', [$this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $datos ?? []));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron listar los entornos');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los entornos', $e->getMessage()));
        }
    }

    public function getEntorno(Request $request, $id)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $this->buscarEntorno((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar el entorno');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el entorno', $e->getMessage()));
        }
    }

    // {nombre, descripcion?, color?, orden?, api_colecciones_id? (null = personal)}.
    public function addEntorno(Request $request)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_ENTORNO_CREAR);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_api_entorno_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $entorno = $this->buscarEntorno((int) $resultado->id);

            $log->logInfo(ApiColaboracionController::class, 'Se creó el entorno ID: ' . $resultado->id);
            $this->avisarEntorno($entorno);

            return response()->json(RespuestaApi::returnResultado('success', 'Entorno creado con éxito', $entorno));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al crear el entorno');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al crear el entorno', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear el entorno', $e->getMessage()));
        }
    }

    // Parcial: {nombre?, descripcion?, color?, orden?} (el ámbito no se cambia).
    public function editEntorno(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $payload = $this->cuerpo($request, self::CAMPOS_ENTORNO_EDITAR);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_api_entorno_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $entorno = $this->buscarEntorno((int) $id);

            $log->logInfo(ApiColaboracionController::class, 'Se actualizó el entorno ID: ' . $id);
            $this->avisarEntorno($entorno);

            return response()->json(RespuestaApi::returnResultado('success', 'Entorno actualizado con éxito', $entorno));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar el entorno');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al actualizar el entorno ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el entorno', $e->getMessage()));
        }
    }

    public function deleteEntorno(Request $request, $id)
    {
        $log = new Funciones();
        try {
            // Se busca antes para saber a qué colección avisar (después de borrar ya no existe).
            $entorno = $this->buscarEntorno((int) $id);

            DB::selectOne('SELECT crm.fn_api_entorno_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            $log->logInfo(ApiColaboracionController::class, 'Se eliminó el entorno ID: ' . $id);
            $this->avisarEntorno($entorno);

            return response()->json(RespuestaApi::returnResultado('success', 'Entorno eliminado con éxito', ['id' => (int) $id]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el entorno');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al eliminar el entorno ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el entorno', $e->getMessage()));
        }
    }

    // {variables: [{id?, nombre, valor, secreta, estado, descripcion?}]}: la lista COMPLETA (lo que no viene se borra;
    // secreta ⇒ valor NULL, cada usuario guarda el suyo con mi-valor). Devuelve el ApiEntorno actualizado.
    public function guardarVariables(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $datos = $this->cuerpo($request, ['variables']);
            $variables = is_array($datos['variables'] ?? null) ? $datos['variables'] : [];

            DB::selectOne('SELECT crm.fn_api_entorno_variables_guardar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'variables' => $this->filtrarVariables($variables),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ], JSON_UNESCAPED_UNICODE)]);
            $entorno = $this->buscarEntorno((int) $id);

            $log->logInfo(ApiColaboracionController::class, 'Se guardaron las variables del entorno ID: ' . $id);
            $this->avisarEntorno($entorno);

            return response()->json(RespuestaApi::returnResultado('success', 'Variables guardadas con éxito', $entorno));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar las variables del entorno');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al guardar las variables del entorno ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar las variables del entorno', $e->getMessage()));
        }
    }

    // Borra una variable. Devuelve {id, entorno: ApiEntorno actualizado}.
    public function deleteVariable(Request $request, $vid)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT api_entornos_id FROM crm.api_entorno_variables WHERE id = ?', [(int) $vid]);
            $entornoId = $fila ? (int) $fila->api_entornos_id : null;

            DB::selectOne('SELECT crm.fn_api_entorno_variable_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $vid,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);
            $entorno = $entornoId ? $this->buscarEntorno($entornoId) : null;

            $log->logInfo(ApiColaboracionController::class, 'Se eliminó la variable ' . $vid . ' del entorno ID: ' . $entornoId);
            $this->avisarEntorno($entorno);

            return response()->json(RespuestaApi::returnResultado('success', 'Variable eliminada con éxito', ['id' => (int) $vid, 'entorno' => $entorno]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar la variable');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al eliminar la variable ID: ' . $vid, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la variable', $e->getMessage()));
        }
    }

    // {valor} en texto plano ('' = borrar el mío). Se cifra aquí con Crypt antes de llamar la función: en la base nunca
    // queda el texto plano. Solo para variables secretas. Devuelve {id, mi_valor_definido}.
    public function guardarMiValor(Request $request, $vid)
    {
        $log = new Funciones();
        try {
            $datos = $this->cuerpo($request, ['valor']);
            $valor = (string) ($datos['valor'] ?? '');

            DB::selectOne('SELECT crm.fn_api_entorno_valor_usuario_guardar(?::jsonb) AS id', [json_encode([
                'id' => (int) $vid,
                'valor_cifrado' => $valor === '' ? '' : Crypt::encryptString($valor),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            $log->logInfo(ApiColaboracionController::class, ($valor === '' ? 'Se borró' : 'Se guardó') . ' el valor secreto del usuario para la variable ID: ' . $vid);

            return response()->json(RespuestaApi::returnResultado('success', $valor === '' ? 'Tu valor se borró' : 'Tu valor se guardó cifrado',
                ['id' => (int) $vid, 'mi_valor_definido' => $valor !== '']));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar tu valor');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al guardar el valor secreto de la variable ID: ' . $vid, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar tu valor', $e->getMessage()));
        }
    }

    // ?revelar=0|1 → {valores: {nombre: valor}, secretas: {nombre: '***' | mi valor descifrado}, secretas_sin_valor: [nombre]}.
    // Solo se descifra lo del propio usuario (la función devuelve únicamente sus valores cifrados).
    // 31-oct (fase 6): + origen_pendiente: [{nombre, request_id, request_nombre, tipo, ruta}] (variables con origen sin respuesta
    // todavía); las secretas con origen resuelto también van dentro de secretas ('***' o su valor).
    public function resolverEntorno(Request $request, $id)
    {
        try {
            $revelar = in_array((string) $request->query('revelar', '0'), ['1', 'true'], true);
            $datos = $this->resolucionEntorno((int) $id, $revelar);

            return response()->json(RespuestaApi::returnResultado('success', 'Entorno resuelto con éxito', $datos));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo resolver el entorno');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo resolver el entorno', $e->getMessage()));
        }
    }

    // Descarga JSON con formato Postman environment (las secretas van con value '' y type 'secret': el secreto nunca sale).
    // Es una descarga (blob en el front): con error responde la envoltura con HTTP 422 para que no se baje como archivo.
    public function exportarEntorno(Request $request, $id)
    {
        try {
            $entorno = $this->buscarEntorno((int) $id);
            $valores = [];
            foreach ($entorno['variables'] ?? [] as $v) {
                $secreta = ($v['secreta'] ?? false) === true;
                $valores[] = [
                    'key' => (string) ($v['nombre'] ?? ''),
                    'value' => $secreta ? '' : (string) ($v['valor'] ?? ''),
                    'type' => $secreta ? 'secret' : 'default',
                    'enabled' => ($v['estado'] ?? true) !== false,
                ];
            }
            $exportado = [
                'id' => (string) Str::uuid(),
                'name' => (string) ($entorno['nombre'] ?? 'entorno'),
                'values' => $valores,
                '_postman_variable_scope' => 'environment',
                '_postman_exported_at' => now()->toIso8601String(),
                '_postman_exported_using' => 'AE-ApiClient/1.0',
            ];
            $nombreArchivo = 'entorno-' . (Str::slug((string) ($entorno['nombre'] ?? '')) ?: (string) (int) $id) . '.json';

            return response()->json($exportado, 200, [
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo exportar el entorno')->setStatusCode(422);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo exportar el entorno', $e->getMessage()), 422);
        }
    }

    // {api_colecciones_id?, nombre?, entorno: <json Postman environment>} (el entorno puede venir como objeto o como texto).
    // Crea el entorno y sus variables (type 'secret' → secreta) en una transacción; devuelve el ApiEntorno creado.
    public function importarEntorno(Request $request)
    {
        $log = new Funciones();
        try {
            $datos = $this->cuerpo($request, ['api_colecciones_id', 'nombre', 'entorno']);
            $importado = $datos['entorno'] ?? null;
            if (is_string($importado)) {
                $importado = json_decode($importado, true);
            }
            if (!is_array($importado) || !is_array($importado['values'] ?? null)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no tiene el formato de un entorno de Postman (falta "values").', null));
            }

            $variables = [];
            foreach ($importado['values'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nombre = trim((string) ($item['key'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                if (!preg_match(self::PATRON_NOMBRE_VARIABLE, $nombre)) {
                    return response()->json(RespuestaApi::returnResultado('error',
                        'La variable "' . $nombre . '" tiene un nombre inválido: solo letras, números, guion, guion bajo y punto.', null));
                }
                $secreta = strtolower((string) ($item['type'] ?? 'default')) === 'secret';
                $variables[] = [
                    'nombre' => $nombre,
                    'valor' => $secreta ? null : $this->aTexto($item['value'] ?? ''),
                    'secreta' => $secreta,
                    'estado' => ($item['enabled'] ?? true) !== false,
                    'descripcion' => isset($item['description']) && is_scalar($item['description']) ? (string) $item['description'] : null,
                ];
            }

            $nombreEntorno = trim((string) ($datos['nombre'] ?? ''));
            if ($nombreEntorno === '') {
                $nombreEntorno = trim((string) ($importado['name'] ?? '')) ?: 'Entorno importado';
            }
            $auditoria = $this->contextoAuditoriaForense($request);

            DB::beginTransaction();
            try {
                $resultado = DB::selectOne('SELECT crm.fn_api_entorno_crear(?::jsonb) AS id', [json_encode([
                    'nombre' => $nombreEntorno,
                    'api_colecciones_id' => isset($datos['api_colecciones_id']) && is_numeric($datos['api_colecciones_id']) ? (int) $datos['api_colecciones_id'] : null,
                    'auditoria' => $auditoria,
                ], JSON_UNESCAPED_UNICODE)]);
                if ($variables) {
                    DB::selectOne('SELECT crm.fn_api_entorno_variables_guardar(?::jsonb) AS id', [json_encode([
                        'id' => (int) $resultado->id,
                        'variables' => $variables,
                        'auditoria' => $auditoria,
                    ], JSON_UNESCAPED_UNICODE)]);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            $entorno = $this->buscarEntorno((int) $resultado->id);

            $log->logInfo(ApiColaboracionController::class, 'Se importó el entorno ID: ' . $resultado->id . ' con ' . count($variables) . ' variables');
            $this->avisarEntorno($entorno);

            return response()->json(RespuestaApi::returnResultado('success', 'Entorno importado con éxito', $entorno));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al importar el entorno');
        } catch (Exception $e) {
            $log->logError(ApiColaboracionController::class, 'Error al importar el entorno', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al importar el entorno', $e->getMessage()));
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

    private function usuariosColeccion(int $id): array
    {
        return $this->leerJsonb('SELECT crm.fn_api_coleccion_usuarios_listar(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]) ?? [];
    }

    private function buscarEntorno(int $id): ?array
    {
        return $this->leerJsonb('SELECT crm.fn_api_entorno_buscar(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]);
    }

    // Cada variable con solo sus campos (el orden lo da la posición en la lista).
    private function filtrarVariables(array $variables): array
    {
        $lista = [];
        foreach ($variables as $v) {
            if (!is_array($v)) {
                continue;
            }
            $lista[] = array_intersect_key($v, array_flip(self::CAMPOS_VARIABLE));
        }

        return $lista;
    }

    // {valores, secretas, secretas_sin_valor} del entorno para el usuario. Las secretas se descifran solo si $revelar; si
    // un valor cifrado no se puede descifrar (APP_KEY distinta) la variable pasa a secretas_sin_valor.
    private function resolucionEntorno(int $id, bool $revelar): array
    {
        $datos = $this->leerJsonb('SELECT crm.fn_api_entorno_resolver(?::bigint, ?::bigint) AS datos', [$id, $this->usuarioId()]) ?? [];
        $valores = is_array($datos['valores'] ?? null) ? $datos['valores'] : [];
        $sinValor = is_array($datos['secretas_sin_valor'] ?? null) ? array_values($datos['secretas_sin_valor']) : [];
        $secretas = [];
        foreach (is_array($datos['secretas'] ?? null) ? $datos['secretas'] : [] as $nombre => $cifrado) {
            if (!$revelar) {
                $secretas[$nombre] = self::MASCARA;
                continue;
            }
            try {
                $secretas[$nombre] = Crypt::decryptString((string) $cifrado);
            } catch (Throwable $e) {
                $sinValor[] = $nombre;
            }
        }
        // 31-oct (fase 6): secretas con origen (valor tomado de la respuesta de otro request, o su respaldo) llegan EN CLARO desde
        // la función: no se descifran; se suman a secretas enmascaradas o reveladas como las demás.
        foreach (is_array($datos['secretas_origen'] ?? null) ? $datos['secretas_origen'] : [] as $nombre => $valor) {
            $secretas[$nombre] = $revelar ? $this->aTexto($valor) : self::MASCARA;
        }
        sort($sinValor);

        // QUITADO 31-oct (fase 6): sin origen_pendiente (reactivar = descomentar)
        // return ['valores' => (object) $valores, 'secretas' => (object) $secretas, 'secretas_sin_valor' => array_values(array_unique($sinValor))];
        return [
            'valores' => (object) $valores,
            'secretas' => (object) $secretas,
            'secretas_sin_valor' => array_values(array_unique($sinValor)),
            // 31-oct (fase 6): variables con origen (secretas o no) sin respuesta exitosa del usuario ni respaldo
            'origen_pendiente' => array_values(is_array($datos['origen_pendiente'] ?? null) ? $datos['origen_pendiente'] : []),
        ];
    }

    // Evento 'entorno' al canal de la colección (solo entornos de colección; los personales no avisan a nadie).
    private function avisarEntorno(?array $entorno): void
    {
        $coleccionId = isset($entorno['api_colecciones_id']) && is_numeric($entorno['api_colecciones_id']) ? (int) $entorno['api_colecciones_id'] : null;
        ApiEventos::coleccion($coleccionId, 'entorno', $this->usuarioId());
    }

    private function aTexto($valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }
        if ($valor === null || is_scalar($valor)) {
            return (string) $valor;
        }

        return (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        (new Funciones())->logError(ApiColaboracionController::class, $generico, $e);

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
