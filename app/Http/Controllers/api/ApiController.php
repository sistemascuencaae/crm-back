<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\api\ApiColeccion;
use App\Models\api\ApiHistorial;
use App\Models\api\ApiRequest;
use App\Models\api\ApiVariable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{
    // ==============================
    // COLECCIONES
    // ==============================

    public function listColecciones()
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            $colecciones = ApiColeccion::with('requests')
                ->where('users_id', $userId)
                ->orderBy('orden', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $log->logInfo(ApiController::class, 'Se listaron las colecciones del usuario: ' . $userId);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $colecciones));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al listar colecciones', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addColeccion(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {
                $userId = Auth::id();
                ApiColeccion::create([
                    'users_id'    => $userId,
                    'nombre'      => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'color'       => $request->color ?? '#0d6efd',
                    'orden'       => $request->orden ?? 0,
                ]);

                return ApiColeccion::with('requests')
                    ->where('users_id', $userId)
                    ->orderBy('orden', 'asc')
                    ->get();
            });

            $log->logInfo(ApiController::class, 'Se creó una colección');

            return response()->json(RespuestaApi::returnResultado('success', 'Colección creada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al crear colección', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editColeccion(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $coleccion = ApiColeccion::findOrFail($id);
                $coleccion->update($request->only(['nombre', 'descripcion', 'color', 'orden']));
                return $coleccion;
            });

            $log->logInfo(ApiController::class, 'Se actualizó la colección ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Colección actualizada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al actualizar colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteColeccion($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id) {
                $coleccion = ApiColeccion::findOrFail($id);
                ApiRequest::where('api_colecciones_id', $id)->delete();
                $coleccion->delete();
                return $coleccion;
            });

            $log->logInfo(ApiController::class, 'Se eliminó la colección ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Colección eliminada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al eliminar colección ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // ==============================
    // REQUESTS
    // ==============================

    public function listRequests($coleccionId)
    {
        $log = new Funciones();
        try {
            $requests = ApiRequest::where('api_colecciones_id', $coleccionId)
                ->orderBy('orden', 'asc')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $requests));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al listar requests de colección: ' . $coleccionId, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addRequest(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {
                return ApiRequest::create([
                    'api_colecciones_id' => $request->api_colecciones_id,
                    'nombre'             => $request->nombre,
                    'metodo'             => $request->metodo,
                    'url'                => $request->url,
                    'headers'            => $request->input('headers', []),
                    'params'             => $request->params ?? [],
                    'body_tipo'          => $request->body_tipo ?? 'none',
                    'body'               => $request->body,
                    'auth_tipo'          => $request->auth_tipo ?? 'none',
                    'auth_data'          => $request->auth_data ?? [],
                    'orden'              => $request->orden ?? 0,
                ]);
            });

            $log->logInfo(ApiController::class, 'Se guardó el request');

            return response()->json(RespuestaApi::returnResultado('success', 'Request guardado con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al guardar request', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editRequest(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $apiRequest = ApiRequest::findOrFail($id);
                $apiRequest->update($request->only([
                    'nombre', 'metodo', 'url', 'headers', 'params',
                    'body_tipo', 'body', 'auth_tipo', 'auth_data', 'orden'
                ]));
                return $apiRequest;
            });

            $log->logInfo(ApiController::class, 'Se actualizó el request ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Request actualizado con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al actualizar request ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteRequest($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id) {
                $apiRequest = ApiRequest::findOrFail($id);
                $apiRequest->delete();
                return $apiRequest;
            });

            $log->logInfo(ApiController::class, 'Se eliminó el request ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Request eliminado con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al eliminar request ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // ==============================
    // HISTORIAL
    // ==============================

    public function listHistorial()
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            $historial = ApiHistorial::where('users_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $historial));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al listar historial', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteHistorial($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id) {
                $historial = ApiHistorial::findOrFail($id);
                $historial->delete();
                return $historial;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Registro eliminado con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al eliminar historial ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function clearHistorial()
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            DB::transaction(function () use ($userId) {
                ApiHistorial::where('users_id', $userId)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Historial limpiado con éxito', []));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al limpiar historial', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // ==============================
    // VARIABLES
    // ==============================

    public function listVariables()
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            $variables = ApiVariable::where('users_id', $userId)
                ->orderBy('nombre', 'asc')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $variables));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al listar variables', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addVariable(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {
                return ApiVariable::create([
                    'users_id'    => Auth::id(),
                    'nombre'      => $request->nombre,
                    'valor'       => $request->valor,
                    'descripcion' => $request->descripcion,
                    'estado'      => $request->estado ?? true,
                ]);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Variable creada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al crear variable', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editVariable(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $variable = ApiVariable::findOrFail($id);
                $variable->update($request->only(['nombre', 'valor', 'descripcion', 'estado']));
                return $variable;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Variable actualizada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al actualizar variable ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteVariable($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id) {
                $variable = ApiVariable::findOrFail($id);
                $variable->delete();
                return $variable;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Variable eliminada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al eliminar variable ID: ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // ==============================
    // PROXY - EJECUTAR REQUEST
    // ==============================

    public function ejecutarRequest(Request $request)
    {
        $log = new Funciones();
        $startTime = microtime(true);

        try {
            $metodo      = strtoupper($request->metodo);
            $url         = $request->url;
            $headers     = $request->input('headers', []);
            $params      = $request->params ?? [];
            $bodyTipo    = $request->body_tipo ?? 'none';
            $body        = $request->body;
            $authTipo    = $request->auth_tipo ?? 'none';
            $authData    = $request->auth_data ?? [];
            $guardarHist = $request->guardar_historial ?? true;
            $requestId   = $request->api_requests_id ?? null;
            $nombre      = $request->nombre ?? null;
            $userId      = Auth::id();

            // Reemplazar variables de entorno {{variable}}
            $variables = ApiVariable::where('users_id', $userId)->where('estado', true)->get();
            foreach ($variables as $variable) {
                $placeholder = '{{' . $variable->nombre . '}}';
                $url  = str_replace($placeholder, $variable->valor, $url);
                $body = $body ? str_replace($placeholder, $variable->valor, $body) : $body;
            }

            // Construir headers
            $headersArray = [];
            foreach ($headers as $header) {
                if (!empty($header['activo']) && !empty($header['key'])) {
                    $headersArray[$header['key']] = $header['value'];
                }
            }

            // Aplicar autenticación
            switch ($authTipo) {
                case 'bearer':
                    $headersArray['Authorization'] = 'Bearer ' . ($authData['token'] ?? '');
                    break;
                case 'basic':
                    $headersArray['Authorization'] = 'Basic ' . base64_encode(($authData['username'] ?? '') . ':' . ($authData['password'] ?? ''));
                    break;
                case 'api-key':
                    if (($authData['in'] ?? 'header') === 'header') {
                        $headersArray[$authData['key'] ?? 'X-API-Key'] = $authData['value'] ?? '';
                    } else {
                        $params[] = ['key' => $authData['key'], 'value' => $authData['value'], 'activo' => true];
                    }
                    break;
                case 'custom':
                    $headersArray[$authData['header'] ?? 'Authorization'] = $authData['value'] ?? '';
                    break;
            }

            // Construir query params
            $queryParams = [];
            foreach ($params as $param) {
                if (!empty($param['activo']) && !empty($param['key'])) {
                    $queryParams[$param['key']] = $param['value'];
                }
            }

            if (!empty($queryParams)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
            }

            // Ejecutar request HTTP
            $httpClient = Http::withHeaders($headersArray)->withOptions(['verify' => false]);

            switch ($metodo) {
                case 'GET':
                    $response = $httpClient->get($url);
                    break;
                case 'DELETE':
                    $response = $httpClient->delete($url);
                    break;
                case 'POST':
                case 'PUT':
                case 'PATCH':
                    if ($bodyTipo === 'form-data' || $bodyTipo === 'urlencoded') {
                        $bodyData = is_string($body) ? json_decode($body, true) : $body;
                        $response = $httpClient->asForm()->{strtolower($metodo)}($url, $bodyData ?? []);
                    } else {
                        $bodyData = is_string($body) ? json_decode($body, true) : $body;
                        $response = $httpClient->asJson()->{strtolower($metodo)}($url, $bodyData ?? []);
                    }
                    break;
                default:
                    $response = $httpClient->get($url);
            }

            $tiempoMs     = round((microtime(true) - $startTime) * 1000);
            $responseBody = $response->body();
            $tamanoKb     = (int) ceil(strlen($responseBody) / 1024);

            // Guardar en historial
            if ($guardarHist) {
                ApiHistorial::create([
                    'users_id'            => $userId,
                    'api_requests_id'     => $requestId,
                    'nombre'              => $nombre,
                    'metodo'              => $metodo,
                    'url'                 => $url,
                    'headers'             => $headers,
                    'params'              => $params,
                    'body_tipo'           => $bodyTipo,
                    'body'                => $body,
                    'auth_tipo'           => $authTipo,
                    'auth_data'           => $authData,
                    'respuesta_status'    => $response->status(),
                    'respuesta_headers'   => $response->headers(),
                    'respuesta_body'      => $responseBody,
                    'respuesta_tiempo_ms' => $tiempoMs,
                    'respuesta_tamano_kb' => $tamanoKb,
                ]);
            }

            $log->logInfo(ApiController::class, 'Request ejecutado: ' . $metodo . ' ' . $url);

            return response()->json(RespuestaApi::returnResultado('success', 'Request ejecutado', [
                'status'    => $response->status(),
                'headers'   => $response->headers(),
                'body'      => $responseBody,
                'tiempo_ms' => $tiempoMs,
                'tamano_kb' => $tamanoKb,
            ]));
        } catch (Exception $e) {
            $log->logError(ApiController::class, 'Error al ejecutar request: ' . $e->getMessage(), $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al ejecutar el request: ' . $e->getMessage(), $e->getMessage()));
        }
    }
}
