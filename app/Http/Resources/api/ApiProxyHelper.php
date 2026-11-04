<?php

namespace App\Http\Resources\api;

use App\Exceptions\ApiCanceladoException;
use App\Http\Resources\crm\Funciones;
use GuzzleHttp\TransferStats; // 30-oct F3: tiempos por transferencia (on_stats)
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt; // 30-oct F4: secretas del entorno
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

// 30-oct: el proxy del API Client. Lee el payload crudo (JSON o la parte "meta" del multipart), resuelve variables
// {{x}} e internas ({{$token_crm}}, {{$timestamp}}, {{$guid}}, {{$randomInt}}), arma headers/auth/query/body, envía con
// Http::send() (cualquier método, con o sin cuerpo), normaliza la respuesta (cookies, archivo, redirecciones) y deja el
// historial en crm.fn_api_historial_registrar sin que un fallo al guardar rompa la respuesta.
// 30-oct F3: desglose de tiempos (on_stats), URL efectiva y respuestas grandes a un temporal descargable por token (1 h).
class ApiProxyHelper
{
    public const METODOS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    public const BODY_TIPOS = ['none', 'raw', 'form-data', 'urlencoded', 'binary', 'graphql'];
    // QUITADO 30-oct F2: sin 'heredar' (reactivar = descomentar)
    // public const AUTH_TIPOS = ['none', 'bearer', 'basic', 'api-key', 'custom', 'oauth2'];
    public const AUTH_TIPOS = ['none', 'heredar', 'bearer', 'basic', 'api-key', 'custom', 'oauth2']; // 30-oct F2: heredar = la auth de la colección
    public const CONTENT_TYPE_POR_SUBTIPO = [
        'json' => 'application/json',
        'xml' => 'application/xml',
        'text' => 'text/plain',
        'javascript' => 'application/javascript',
        'html' => 'text/html',
    ];
    public const TIMEOUT_MINIMO = 5;
    public const TIMEOUT_DEFECTO = 60;
    public const AGENTE = 'AE-ApiClient/1.0';
    public const RESPUESTA_MAX_MB_DEFECTO = 20; // 30-oct F3: por encima, el cuerpo queda en un temporal descargable
    public const TEMPORAL_VIDA_SEG = 3600;      // 30-oct F3: vida de esos temporales (1 h)

    private const MENSAJES_CORTOS = [
        'timeout' => 'El servidor destino no respondió a tiempo',
        'conexion' => 'No se pudo conectar con el servidor destino',
        'ssl' => 'Problema con el certificado SSL del servidor destino',
        'cancelado' => 'Petición cancelada',
        'validacion' => 'Petición inválida',
        'proxy' => 'Error al ejecutar la petición',
    ];

    private array $variables = [];
    private array $sinResolver = [];
    private array $origenPendiente = []; // 31-oct (fase 6): variables con origen (desde la respuesta de otro request) sin respuesta todavía
    private array $secretasSinValor = []; // 31-oct (fase 6): secretas del entorno sin «mi valor»: mandan sobre globales/colección con el mismo nombre
    private array $coleccion = []; // 30-oct F2: {auth_tipo, auth_data, headers} de la colección del request (vacío si no hay o sin permiso)
    private array $transferencias = []; // 30-oct F3: curl_getinfo de cada transferencia (una por redirección), en orden
    private ?string $urlEfectiva = null; // 30-oct F3: URI efectiva de la última transferencia (url_final)
    private Funciones $log;

    public function __construct()
    {
        $this->log = new Funciones();
    }

    public static function claveCancelar(string $uid): string
    {
        return 'api_client_cancelar_' . $uid;
    }

    // Devuelve ['status' => 'success'|'error', 'message' => texto corto, 'data' => RespuestaProxy|ErrorProxy].
    public function ejecutar(Request $request): array
    {
        self::limpiarTemporales(); // 30-oct F3: respuestas grandes con más de 1 h
        $payload = $this->leerPayload($request);
        $archivos = $this->archivos($request);

        $error = $this->validar($payload, $archivos);
        if ($error !== null) {
            return $this->resultadoError('validacion', $error, 0, null);
        }

        $p = $this->normalizar($payload);
        $uid = $p['uid'];
        $usuarioId = ($u = auth('api')->user()) ? (int) $u->id : null;

        $this->cargarVariables($usuarioId, $p['api_colecciones_id'], $p['entorno_id'], $request->bearerToken());
        $this->cargarColeccion($p['api_colecciones_id'], $usuarioId); // 30-oct F2: auth 'heredar' y headers de la colección

        // Variables en url, params, headers, auth y body
        $url = $this->normalizarUrl($this->resolver($p['url']));
        $partesUrl = parse_url($url);
        $host = is_array($partesUrl) ? (string) ($partesUrl['host'] ?? '') : '';
        if ($partesUrl === false || $host === '' || strpos($host, '{{') !== false) {
            // Con el host sin resolver la petición no puede salir: aviso claro en vez de "Bad hostname" de cURL.
            preg_match_all('/\{\{\s*(\$?[\w.\-]+)\s*\}\}/', $url, $m);
            $sinValor = array_values(array_unique($m[1]));

            // QUITADO 31-oct (fase 6): el error no decía qué variables faltaban (reactivar = descomentar)
            // return $this->resultadoError('validacion', $sinValor
            //     ? 'La URL tiene variables sin valor: {{' . implode('}}, {{', $sinValor) . '}}'
            //     : 'La URL no es válida.', 0, null);
            $resultado = $this->resultadoError('validacion', $sinValor
                ? 'La URL tiene variables sin valor: {{' . implode('}}, {{', $sinValor) . '}}'
                : 'La URL no es válida.', 0, null);
            // 31-oct (fase 6): también aquí el front sabe qué variables faltan y cuáles se llenan ejecutando otro request
            $resultado['data']['variables_sin_resolver'] = $sinValor;
            $resultado['data']['variables_origen_pendiente'] = $this->origenPendienteUsado($sinValor);

            return $resultado;
        }
        $paramsFilas = $this->resolverFilas($p['params']);
        $headersFilas = $this->resolverFilas($p['headers']);
        // QUITADO 30-oct F2: la auth era siempre la del request (reactivar = descomentar)
        // $authData = $this->resolverProfundo($p['auth_data']);
        [$authTipo, $authData] = $this->authEfectiva($p['auth_tipo'], $p['auth_data']);
        $authData = $this->resolverProfundo($authData);

        $pares = [];
        foreach ($this->filasActivas($paramsFilas) as $f) {
            $pares[] = [trim((string) $f['key']), (string) ($f['value'] ?? '')];
        }

        try {
            // QUITADO 30-oct F2: sin los headers de la colección ni la auth heredada (reactivar = descomentar)
            // $h = $this->armarHeaders($headersFilas);
            // $this->aplicarAuth($h, $pares, $p['auth_tipo'], $authData);
            $h = $this->armarHeaders($headersFilas, $this->resolverFilas($this->headersColeccion()));
            $this->aplicarAuth($h, $pares, $authTipo, $authData);
            $urlEnvio = $this->conQuery($url, $pares);

            $cliente = Http::withOptions([
                'verify' => $p['opciones']['verificar_ssl'],
                'timeout' => $p['opciones']['timeout_seg'],
                'connect_timeout' => min(10, $p['opciones']['timeout_seg']),
                'allow_redirects' => $p['opciones']['seguir_redirecciones']
                    ? ['max' => $p['opciones']['max_redirecciones'], 'track_redirects' => true]
                    : false,
                'http_errors' => false,
                'curl' => [CURLOPT_NOPROGRESS => false, CURLOPT_PROGRESSFUNCTION => $this->callbackCancelacion($uid)],
            ]);
            [$cliente, $opcionesEnvio] = $this->prepararCuerpo($cliente, $p, $archivos, $h);
            // 30-oct F3: tiempos y URL efectiva. Va en las opciones de send(): Laravel pone su propio on_stats al enviar
            // y pisaría al de withOptions.
            $opcionesEnvio['on_stats'] = $this->callbackEstadisticas();
        } catch (InvalidArgumentException $e) {
            return $this->resultadoError('validacion', $e->getMessage(), 0, null);
        }

        Cache::forget(self::claveCancelar($uid));
        @set_time_limit($p['opciones']['timeout_seg'] + 30);

        $inicio = microtime(true);
        $resultado = null;
        $data = null;
        $errorTipo = null;
        try {
            $respuesta = $cliente->send($p['metodo'], $urlEnvio, $opcionesEnvio);
            $data = $this->armarRespuesta($respuesta, $urlEnvio, $inicio);
            $data['variables_sin_resolver'] = $this->sinResolver;
            $data['variables_origen_pendiente'] = $this->origenPendienteUsado(); // 31-oct (fase 6)
            $resultado = ['status' => 'success', 'message' => 'Request ejecutado', 'data' => $data];
            $this->log->logInfo(ApiProxyHelper::class, 'Request ejecutado: ' . $p['metodo'] . ' ' . $url . ' -> ' . $data['status']);
        } catch (Throwable $e) {
            $errorTipo = $this->tipoError($e, $uid);
            if ($errorTipo === 'cancelado' && !$e instanceof ApiCanceladoException) {
                $e = new ApiCanceladoException('Petición cancelada por el usuario', $e);
            }
            $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);
            $resultado = $this->resultadoError($errorTipo, $e->getMessage(), $tiempoMs, null);
            $this->log->logError(ApiProxyHelper::class, 'Request con error (' . $errorTipo . '): ' . $p['metodo'] . ' ' . $url, $e);
        }
        Cache::forget(self::claveCancelar($uid));

        if ($p['guardar_historial']) {
            [$historialId, $truncado] = $this->registrarHistorial($p, $url, $data, $errorTipo, $resultado['data']['tiempo_ms'] ?? 0, $usuarioId);
            $resultado['data']['historial_id'] = $historialId;
            if ($data !== null) {
                $resultado['data']['truncado_en_historial'] = $truncado;
            }
        }

        return $resultado;
    }

    // ------------------------------------------------------------------
    // Entrada
    // ------------------------------------------------------------------

    // JSON crudo (getContent) o la parte "meta" del multipart: nunca $request->all(), que pasa por TrimStrings y
    // ConvertEmptyStringsToNull.
    public function leerPayload(Request $request): array
    {
        if ($request->isJson()) {
            $datos = json_decode($request->getContent(), true);
        } else {
            $meta = $request->input('meta');
            $datos = is_string($meta) ? json_decode($meta, true) : (is_array($meta) ? $meta : null);
        }

        return is_array($datos) ? $datos : [];
    }

    private function archivos(Request $request): array
    {
        $archivos = $request->file('archivos');
        if ($archivos instanceof UploadedFile) {
            $archivos = [$archivos];
        }
        if (!is_array($archivos)) {
            return [];
        }

        return array_values(array_filter($archivos, fn ($a) => $a instanceof UploadedFile));
    }

    private function validar(array $p, array $archivos): ?string
    {
        if (trim((string) ($p['url'] ?? '')) === '') {
            return 'Ingresa la URL de la petición.';
        }
        if (!in_array(strtoupper(trim((string) ($p['metodo'] ?? ''))), self::METODOS, true)) {
            return 'El método HTTP no es válido.';
        }
        $bodyTipo = $p['body_tipo'] ?? 'none';
        if (!in_array($bodyTipo, self::BODY_TIPOS, true)) {
            return 'El tipo de body no es válido.';
        }
        if (!in_array($p['auth_tipo'] ?? 'none', self::AUTH_TIPOS, true)) {
            return 'El tipo de autenticación no es válido.';
        }
        $timeout = $p['opciones']['timeout_seg'] ?? null;
        $maximo = $this->timeoutMaximo();
        if ($timeout !== null && $timeout !== '' && (!is_numeric($timeout) || $timeout < self::TIMEOUT_MINIMO || $timeout > $maximo)) {
            return 'El tiempo de espera debe estar entre ' . self::TIMEOUT_MINIMO . ' y ' . $maximo . ' segundos.';
        }
        if ($archivos) {
            $maxMb = (int) env('API_CLIENT_ARCHIVO_MAX_MB', 50);
            $v = Validator::make(['archivos' => $archivos], ['archivos.*' => 'file|max:' . ($maxMb * 1024)]);
            if ($v->fails()) {
                return 'Uno de los archivos no es válido o supera los ' . $maxMb . ' MB.';
            }
        }
        if ($bodyTipo === 'binary' && !$archivos) {
            return 'Elige el archivo que se enviará como body binario.';
        }

        return null;
    }

    private function timeoutMaximo(): int
    {
        return max(self::TIMEOUT_MINIMO, (int) env('API_CLIENT_TIMEOUT_MAX_SEG', 600));
    }

    // El payload con tipos y valores por defecto ya resueltos.
    private function normalizar(array $p): array
    {
        $uid = preg_replace('/[^\w\-]/', '', (string) ($p['uid'] ?? ''));
        $opciones = is_array($p['opciones'] ?? null) ? $p['opciones'] : [];
        $timeout = $opciones['timeout_seg'] ?? null;
        $timeout = $timeout === null || $timeout === '' ? self::TIMEOUT_DEFECTO : (int) $timeout;
        $maxRedir = $opciones['max_redirecciones'] ?? null;
        $maxRedir = $maxRedir === null || $maxRedir === '' || !is_numeric($maxRedir) ? 5 : min(max((int) $maxRedir, 1), 20);

        return [
            'uid' => $uid !== '' ? $uid : (string) Str::uuid(),
            'nombre' => isset($p['nombre']) && trim((string) $p['nombre']) !== '' ? (string) $p['nombre'] : null,
            'metodo' => strtoupper(trim((string) $p['metodo'])),
            'url' => trim((string) $p['url']),
            'params' => is_array($p['params'] ?? null) ? $p['params'] : [],
            'headers' => is_array($p['headers'] ?? null) ? $p['headers'] : [],
            'body_tipo' => $p['body_tipo'] ?? 'none',
            'body_subtipo' => isset($p['body_subtipo']) ? (string) $p['body_subtipo'] : null,
            'body' => isset($p['body']) ? (string) $p['body'] : null,
            'auth_tipo' => $p['auth_tipo'] ?? 'none',
            'auth_data' => is_array($p['auth_data'] ?? null) ? $p['auth_data'] : [],
            'opciones' => [
                'timeout_seg' => $timeout,
                'verificar_ssl' => (bool) ($opciones['verificar_ssl'] ?? false),
                'seguir_redirecciones' => (bool) ($opciones['seguir_redirecciones'] ?? true),
                'max_redirecciones' => $maxRedir,
            ],
            'api_requests_id' => isset($p['api_requests_id']) && is_numeric($p['api_requests_id']) ? (int) $p['api_requests_id'] : null,
            'api_colecciones_id' => isset($p['api_colecciones_id']) && is_numeric($p['api_colecciones_id']) ? (int) $p['api_colecciones_id'] : null,
            'entorno_id' => isset($p['entorno_id']) && is_numeric($p['entorno_id']) ? (int) $p['entorno_id'] : null,
            'guardar_historial' => (bool) ($p['guardar_historial'] ?? true),
        ];
    }

    // ------------------------------------------------------------------
    // Variables {{x}}
    // ------------------------------------------------------------------

    private function cargarVariables(?int $usuarioId, ?int $coleccionId, ?int $entornoId, ?string $tokenCrm): void
    {
        $this->variables = [];
        $this->sinResolver = [];
        $this->origenPendiente = []; // 31-oct (fase 6)
        $this->secretasSinValor = []; // 31-oct (fase 6)
        try {
            $fila = DB::selectOne('SELECT crm.fn_api_variable_resolver(?::bigint, ?::bigint, ?::bigint) AS v', [$usuarioId, $coleccionId, $entornoId]);
            $vars = $fila && $fila->v !== null ? json_decode($fila->v, true) : [];
            foreach (is_array($vars) ? $vars : [] as $nombre => $valor) {
                $this->variables[(string) $nombre] = $this->aTexto($valor);
            }
        } catch (Throwable $e) {
            $this->log->logError(ApiProxyHelper::class, 'No se pudieron resolver las variables del usuario', $e);
        }
        // 30-oct F4: el entorno activo pisa a las globales (precedencia entorno > globales).
        if ($entornoId !== null && $usuarioId !== null) {
            $this->aplicarEntorno($entornoId, $usuarioId);
        }
        // 31-oct (fase 6): variables activas con origen (globales, de la colección y del entorno) cuyo request todavía no tiene una
        // respuesta exitosa del usuario y sin respaldo: [{nombre, ambito, ambito_nombre, request_id, request_nombre, tipo, ruta}].
        // No están en el mapa (el literal {{x}} se conserva); el front sabe así qué request ejecutar primero.
        try {
            $fila = DB::selectOne('SELECT crm.fn_api_variable_origen_pendientes(?::bigint, ?::bigint, ?::bigint) AS v', [$usuarioId, $coleccionId, $entornoId]);
            $pendientes = $fila && $fila->v !== null ? json_decode($fila->v, true) : [];
            $this->origenPendiente = is_array($pendientes) ? array_values(array_filter($pendientes, 'is_array')) : [];
        } catch (Throwable $e) {
            $this->origenPendiente = [];
            $this->log->logError(ApiProxyHelper::class, 'No se pudieron leer las variables con origen pendiente', $e);
        }
        // Internas: se resuelven en el servidor, el token del CRM nunca viaja ni se guarda.
        $this->variables['$token_crm'] = (string) $tokenCrm;
        $this->variables['$timestamp'] = (string) time();
        $this->variables['$guid'] = (string) Str::uuid();
        $this->variables['$randomInt'] = (string) random_int(0, 1000);
    }

    // 30-oct F4: crm.fn_api_entorno_resolver → {valores, secretas (cifradas, solo las del usuario), secretas_sin_valor}.
    // Los valores pisan a las globales; las secretas se descifran con Crypt (si falla, esa variable queda sin resolver);
    // las secretas que el usuario no definió quedan sin resolver aunque exista una global con el mismo nombre, así salen
    // en variables_sin_resolver. Si el entorno no se puede leer (no existe, sin permiso) siguen solo las globales.
    // 31-oct (fase 6): además secretas_origen ({nombre: valor EN CLARO} de las secretas con origen resuelto: no se descifran) y
    // origen_pendiente ([{nombre, ...}] con origen sin respuesta ni respaldo: se quitan del mapa, como secretas_sin_valor).
    private function aplicarEntorno(int $entornoId, int $usuarioId): void
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_api_entorno_resolver(?::bigint, ?::bigint) AS datos', [$entornoId, $usuarioId]);
            $datos = $fila && $fila->datos !== null ? json_decode($fila->datos, true) : [];
        } catch (Throwable $e) {
            $this->log->logError(ApiProxyHelper::class, 'No se pudo resolver el entorno ' . $entornoId . ' (siguen solo las globales)', $e);

            return;
        }
        $datos = is_array($datos) ? $datos : [];
        foreach (is_array($datos['valores'] ?? null) ? $datos['valores'] : [] as $nombre => $valor) {
            $this->variables[(string) $nombre] = $this->aTexto($valor);
        }
        foreach (is_array($datos['secretas'] ?? null) ? $datos['secretas'] : [] as $nombre => $cifrado) {
            try {
                $this->variables[(string) $nombre] = Crypt::decryptString((string) $cifrado);
            } catch (Throwable $e) {
                unset($this->variables[(string) $nombre]);
            }
        }
        // 31-oct (fase 6): secretas con origen (valor de la respuesta de otro request, o su respaldo) llegan EN CLARO: sin Crypt.
        foreach (is_array($datos['secretas_origen'] ?? null) ? $datos['secretas_origen'] : [] as $nombre => $valor) {
            $this->variables[(string) $nombre] = $this->aTexto($valor);
        }
        foreach (is_array($datos['secretas_sin_valor'] ?? null) ? $datos['secretas_sin_valor'] : [] as $nombre) {
            unset($this->variables[(string) $nombre]);
            $this->secretasSinValor[] = (string) $nombre; // 31-oct (fase 6): sin aviso de origen pendiente para este nombre
        }
        // 31-oct (fase 6): con origen pero sin respuesta todavía (ni respaldo): fuera del mapa para que {{x}} quede literal y salga
        // en variables_sin_resolver, aunque exista una global o de colección con el mismo nombre (tapa, como secretas_sin_valor).
        foreach (is_array($datos['origen_pendiente'] ?? null) ? $datos['origen_pendiente'] : [] as $item) {
            $nombre = is_array($item) ? (string) ($item['nombre'] ?? '') : (string) $item;
            if ($nombre !== '') {
                unset($this->variables[$nombre]);
            }
        }
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

    // 31-oct (fase 6): de las variables con origen pendiente, solo las que esta petición usó de verdad (su nombre quedó en
    // variables_sin_resolver, o en la lista que se pasa): [{nombre, ambito, ambito_nombre, request_id, request_nombre, tipo, ruta}].
    // 31-oct (fase 6): UN item por nombre (la función SQL da uno por ámbito): el del ámbito que manda al resolver, entorno > coleccion > global.
    // 31-oct (fase 6): sin los nombres de secretas_sin_valor del entorno: esa secreta manda y ejecutar el request de la global/colección no
    // la llena (el aviso apuntaría al request equivocado); quedan solo en variables_sin_resolver (aviso genérico).
    private function origenPendienteUsado(?array $nombres = null): array
    {
        $nombres = $nombres ?? $this->sinResolver;

        // QUITADO 31-oct: el mismo nombre pendiente en dos ámbitos salía dos veces (reactivar = descomentar)
        // return array_values(array_filter($this->origenPendiente,
        //     fn ($item) => is_array($item) && in_array((string) ($item['nombre'] ?? ''), $nombres, true)));
        // QUITADO 31-oct: no excluía las secretas del entorno sin «mi valor» (reactivar = descomentar)
        // $usados = array_values(array_filter($this->origenPendiente,
        //     fn ($item) => is_array($item) && in_array((string) ($item['nombre'] ?? ''), $nombres, true)));
        $usados = array_values(array_filter($this->origenPendiente,
            fn ($item) => is_array($item) && in_array((string) ($item['nombre'] ?? ''), $nombres, true)
                && !in_array((string) ($item['nombre'] ?? ''), $this->secretasSinValor, true)));
        $prioridad = ['entorno' => 0, 'coleccion' => 1, 'global' => 2];
        // Orden estable por prioridad (el índice desempata: usort no es estable antes de PHP 8) y queda el primero de cada nombre.
        $indices = array_keys($usados);
        usort($indices, fn ($a, $b) => [$prioridad[(string) ($usados[$a]['ambito'] ?? '')] ?? 3, $a]
            <=> [$prioridad[(string) ($usados[$b]['ambito'] ?? '')] ?? 3, $b]);
        $porNombre = [];
        foreach ($indices as $i) {
            $nombre = (string) ($usados[$i]['nombre'] ?? '');
            if (!array_key_exists($nombre, $porNombre)) {
                $porNombre[$nombre] = $usados[$i];
            }
        }

        return array_values($porNombre);
    }

    // ------------------------------------------------------------------
    // 30-oct F2: auth 'heredar' y headers de la colección
    // ------------------------------------------------------------------

    // crm.fn_api_coleccion_auth → {auth_tipo, auth_data, headers}. Si no hay colección, no existe o el usuario no la
    // puede ver, queda vacío: 'heredar' pasa a 'none' y no se suma ningún header (la petición sale igual).
    private function cargarColeccion(?int $coleccionId, ?int $usuarioId): void
    {
        $this->coleccion = [];
        if ($coleccionId === null || $usuarioId === null) {
            return;
        }
        try {
            $fila = DB::selectOne('SELECT crm.fn_api_coleccion_auth(?::bigint, ?::bigint) AS datos', [$coleccionId, $usuarioId]);
            $datos = $fila && $fila->datos !== null ? json_decode($fila->datos, true) : null;
            $this->coleccion = is_array($datos) ? $datos : [];
        } catch (Throwable $e) {
            $this->log->logInfo(ApiProxyHelper::class, 'No se pudo leer la auth de la colección ' . $coleccionId . ' (sigue sin heredar): ' . $e->getMessage());
        }
    }

    // [tipo, auth_data] que se aplica: el del request, salvo 'heredar', que toma el de la colección (sin colección = none).
    private function authEfectiva(string $tipo, array $auth): array
    {
        if ($tipo !== 'heredar') {
            return [$tipo, $auth];
        }
        $tipoColeccion = (string) ($this->coleccion['auth_tipo'] ?? 'none');
        if ($tipoColeccion === 'heredar' || !in_array($tipoColeccion, self::AUTH_TIPOS, true)) {
            return ['none', []];
        }

        return [$tipoColeccion, is_array($this->coleccion['auth_data'] ?? null) ? $this->coleccion['auth_data'] : []];
    }

    private function headersColeccion(): array
    {
        return is_array($this->coleccion['headers'] ?? null) ? $this->coleccion['headers'] : [];
    }

    // Reemplaza {{nombre}} y {{$interna}}; lo que no existe se deja tal cual y se anota en variables_sin_resolver.
    private function resolver($texto): string
    {
        $texto = (string) $texto;
        if ($texto === '' || strpos($texto, '{{') === false) {
            return $texto;
        }

        return preg_replace_callback('/\{\{\s*(\$?[\w.\-]+)\s*\}\}/', function ($m) {
            $nombre = $m[1];
            if (array_key_exists($nombre, $this->variables)) {
                return $this->variables[$nombre];
            }
            if (!in_array($nombre, $this->sinResolver, true)) {
                $this->sinResolver[] = $nombre;
            }

            return $m[0];
        }, $texto);
    }

    private function resolverFilas(array $filas): array
    {
        return array_map(function ($f) {
            if (!is_array($f)) {
                return $f;
            }
            if (array_key_exists('key', $f)) {
                $f['key'] = $this->resolver($f['key']);
            }
            if (array_key_exists('value', $f) && ($f['tipo'] ?? 'text') !== 'file') {
                $f['value'] = $this->resolver($f['value']);
            }

            return $f;
        }, $filas);
    }

    private function resolverProfundo(array $datos): array
    {
        array_walk_recursive($datos, function (&$v) {
            if (is_string($v)) {
                $v = $this->resolver($v);
            }
        });

        return $datos;
    }

    private function filasActivas(array $filas): array
    {
        return array_values(array_filter($filas, fn ($f) => is_array($f) && ($f['activo'] ?? true) !== false && trim((string) ($f['key'] ?? '')) !== ''));
    }

    // ------------------------------------------------------------------
    // Headers, auth y query
    // ------------------------------------------------------------------

    // 30-oct F2: los headers activos de la colección van debajo de los del request (si repiten nombre gana el request) y
    // antes de los por defecto (User-Agent, Accept).
    private function armarHeaders(array $headersFilas, array $headersColeccion = []): array
    {
        $h = [];
        foreach ($this->filasActivas($headersFilas) as $f) {
            $nombre = trim((string) $f['key']);
            if (!preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $nombre)) {
                throw new InvalidArgumentException('El header "' . $nombre . '" no tiene un nombre válido.');
            }
            $this->ponerHeader($h, $nombre, str_replace(["\r", "\n"], '', (string) ($f['value'] ?? '')));
        }
        foreach ($this->filasActivas($headersColeccion) as $f) {
            $nombre = trim((string) $f['key']);
            if ($this->valorHeader($h, $nombre) !== null) {
                continue; // el request ya lo trae: gana el request
            }
            if (!preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $nombre)) {
                throw new InvalidArgumentException('El header "' . $nombre . '" de la colección no tiene un nombre válido.');
            }
            $this->ponerHeader($h, $nombre, str_replace(["\r", "\n"], '', (string) ($f['value'] ?? '')));
        }
        if ($this->valorHeader($h, 'User-Agent') === null) {
            $h['User-Agent'] = self::AGENTE;
        }
        if ($this->valorHeader($h, 'Accept') === null) {
            $h['Accept'] = '*/*';
        }

        return $h;
    }

    private function aplicarAuth(array &$h, array &$pares, string $tipo, array $auth): void
    {
        switch ($tipo) {
            case 'bearer':
                $this->ponerHeader($h, 'Authorization', 'Bearer ' . trim((string) ($auth['token'] ?? '')));
                break;
            case 'basic':
                $this->ponerHeader($h, 'Authorization', 'Basic ' . base64_encode(($auth['username'] ?? '') . ':' . ($auth['password'] ?? '')));
                break;
            case 'api-key':
                $clave = trim((string) ($auth['key'] ?? '')) ?: 'X-API-Key';
                $valor = (string) ($auth['value'] ?? '');
                if (($auth['in'] ?? 'header') === 'query') {
                    $pares[] = [$clave, $valor];
                } else {
                    $this->ponerHeader($h, $clave, $valor);
                }
                break;
            case 'custom':
                $this->ponerHeader($h, trim((string) ($auth['header'] ?? '')) ?: 'Authorization', (string) ($auth['value'] ?? ''));
                break;
            case 'oauth2':
                if (trim((string) ($auth['access_token'] ?? '')) !== '') {
                    $this->ponerHeader($h, 'Authorization', 'Bearer ' . trim((string) $auth['access_token']));
                }
                break;
        }
    }

    private function ponerHeader(array &$h, string $nombre, string $valor): void
    {
        $this->quitarHeader($h, $nombre);
        $h[$nombre] = $valor;
    }

    private function quitarHeader(array &$h, string $nombre): void
    {
        foreach (array_keys($h) as $k) {
            if (strcasecmp((string) $k, $nombre) === 0) {
                unset($h[$k]);
            }
        }
    }

    private function valorHeader(array $h, string $nombre): ?string
    {
        foreach ($h as $k => $v) {
            if (strcasecmp((string) $k, $nombre) === 0) {
                return (string) $v;
            }
        }

        return null;
    }

    // Agrega los params activos a la URL respetando el "?" existente; un value vacío sí viaja (key=). Los pares que la
    // URL ya trae (el front sincroniza URL <-> Params) no se repiten.
    private function conQuery(string $url, array $pares): string
    {
        if (!$pares) {
            return $url;
        }
        $existentes = [];
        $pos = strpos($url, '?');
        if ($pos !== false) {
            foreach (explode('&', substr($url, $pos + 1)) as $trozo) {
                if ($trozo === '') {
                    continue;
                }
                $kv = explode('=', $trozo, 2);
                $existentes[] = urldecode($kv[0]) . '=' . urldecode($kv[1] ?? '');
            }
        }
        $nuevos = [];
        foreach ($pares as [$k, $v]) {
            if (in_array($k . '=' . $v, $existentes, true)) {
                continue;
            }
            $nuevos[] = http_build_query([$k => $v]);
        }
        if (!$nuevos) {
            return $url;
        }
        $qs = implode('&', $nuevos);
        if ($pos === false) {
            return $url . '?' . $qs;
        }

        return $url . (in_array(substr($url, -1), ['?', '&'], true) ? '' : '&') . $qs;
    }

    // ------------------------------------------------------------------
    // Cuerpo
    // ------------------------------------------------------------------

    // Devuelve [PendingRequest, opciones para send()]. Cuando el tipo de body define el Content-Type, el header del
    // usuario (si lo puso) se usa como ese Content-Type y se quita de la lista para no mandarlo dos veces.
    private function prepararCuerpo(PendingRequest $cliente, array $p, array $archivos, array $h): array
    {
        $tipo = $p['body_tipo'];
        $ctUsuario = $this->valorHeader($h, 'Content-Type');
        if ($tipo !== 'none') {
            $this->quitarHeader($h, 'Content-Type');
        }
        $cliente = $cliente->withHeaders($h);

        switch ($tipo) {
            case 'raw':
                $ct = $ctUsuario ?: (self::CONTENT_TYPE_POR_SUBTIPO[$p['body_subtipo'] ?? ''] ?? 'text/plain');

                return [$cliente->withBody($this->resolver($p['body'] ?? ''), $ct), []];

            case 'urlencoded':
                $form = [];
                foreach ($this->filasActivas($this->resolverFilas($this->filasDesdeBody($p['body']))) as $f) {
                    $form[trim((string) $f['key'])] = (string) ($f['value'] ?? '');
                }

                return [$cliente->asForm(), ['form_params' => $form]];

            case 'form-data':
                $partes = [];
                foreach ($this->filasActivas($this->resolverFilas($this->filasDesdeBody($p['body']))) as $f) {
                    $nombre = trim((string) $f['key']);
                    if (($f['tipo'] ?? 'text') === 'file') {
                        $indice = $f['indice_archivo'] ?? null;
                        $archivo = $indice !== null && is_numeric($indice) ? ($archivos[(int) $indice] ?? null) : null;
                        if (!$archivo) {
                            continue; // fila de archivo sin archivo elegido en esta sesión: no viaja
                        }
                        $partes[] = [
                            'name' => $nombre,
                            'contents' => fopen($archivo->getRealPath(), 'r'),
                            'filename' => $archivo->getClientOriginalName(),
                            'headers' => ['Content-Type' => $archivo->getMimeType() ?: 'application/octet-stream'],
                        ];
                    } else {
                        $partes[] = ['name' => $nombre, 'contents' => (string) ($f['value'] ?? '')];
                    }
                }

                return [$cliente->asMultipart(), ['multipart' => $partes]];

            case 'binary':
                $info = json_decode((string) $p['body'], true);
                $indice = is_array($info) && isset($info['indice_archivo']) && is_numeric($info['indice_archivo']) ? (int) $info['indice_archivo'] : 0;
                $archivo = $archivos[$indice] ?? $archivos[0];
                $ct = $ctUsuario ?: ($archivo->getMimeType() ?: 'application/octet-stream');

                return [$cliente->withBody((string) file_get_contents($archivo->getRealPath()), $ct), []];

            case 'graphql':
                $g = json_decode((string) $p['body'], true);
                $g = is_array($g) ? $g : [];
                $query = $this->resolver($g['query'] ?? '');
                $variables = $g['variables'] ?? null;
                if (is_string($variables)) {
                    $texto = trim($this->resolver($variables));
                    $variables = $texto === '' ? null : json_decode($texto);
                    if ($variables === null && $texto !== '' && strtolower($texto) !== 'null') {
                        throw new InvalidArgumentException('Las variables de GraphQL deben ser un JSON válido.');
                    }
                } elseif ($variables !== null) {
                    $variables = json_decode((string) json_encode($variables));
                }

                return [$cliente->withBody((string) json_encode(['query' => $query, 'variables' => $variables], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $ctUsuario ?: 'application/json'), []];

            default:
                return [$cliente, []];
        }
    }

    // Filas [{key, value, activo, tipo?...}] desde el JSON del body; si viene un objeto plano (requests viejos guardados
    // como {"a":"b"}) se convierte en filas.
    private function filasDesdeBody(?string $body): array
    {
        $datos = json_decode((string) $body, true);
        if (!is_array($datos)) {
            return [];
        }
        if (array_keys($datos) !== range(0, count($datos) - 1)) {
            $filas = [];
            foreach ($datos as $k => $v) {
                $filas[] = ['key' => (string) $k, 'value' => $this->aTexto($v), 'activo' => true];
            }

            return $filas;
        }

        return $datos;
    }

    // ------------------------------------------------------------------
    // Cancelación
    // ------------------------------------------------------------------

    // libcurl llama al callback de progreso ~1 vez por segundo aunque no haya tráfico; devolver 1 aborta la transferencia
    // (cURL error 42 "Callback aborted") y el proxy lo traduce a 'cancelado'. Se mira la marca en Cache como mucho cada
    // medio segundo. (La opción "progress" de Guzzle no sirve para esto: ignora el valor de retorno y una excepción
    // lanzada adentro queda pendiente hasta que termina la transferencia.)
    private function callbackCancelacion(string $uid): \Closure
    {
        $ultimaRevision = 0.0;
        $cancelado = false;

        return function () use ($uid, &$ultimaRevision, &$cancelado): int {
            if ($cancelado) {
                return 1;
            }
            $ahora = microtime(true);
            if ($ahora - $ultimaRevision < 0.5) {
                return 0;
            }
            $ultimaRevision = $ahora;
            if (Cache::has(self::claveCancelar($uid))) {
                $cancelado = true;

                return 1;
            }

            return 0;
        };
    }

    // Sin esquema se asume http:// (como Postman).
    private function normalizarUrl(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
            $url = 'http://' . $url;
        }

        return $url;
    }

    // ------------------------------------------------------------------
    // Respuesta
    // ------------------------------------------------------------------

    private function armarRespuesta(Response $respuesta, string $urlPedida, float $inicio): array
    {
        $cuerpo = $respuesta->body();
        $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);
        $psr = $respuesta->toPsrResponse();

        $headers = [];
        $cookies = [];
        $redirecciones = [];
        foreach ($psr->getHeaders() as $nombre => $valores) {
            $minus = strtolower((string) $nombre);
            if ($minus === 'x-guzzle-redirect-history') {
                $redirecciones = $valores;
                continue;
            }
            if ($minus === 'x-guzzle-redirect-status-history') {
                continue;
            }
            if ($minus === 'set-cookie') {
                foreach ($valores as $v) {
                    $cookies[] = self::parsearCookie((string) $v);
                }
                $headers[$nombre] = $this->textoSeguro(implode("\n", $valores)); // una cookie por línea, no se unen con coma
                continue;
            }
            $headers[$nombre] = $this->textoSeguro(implode(', ', $valores));
        }

        $contentType = $respuesta->header('Content-Type');
        $esArchivo = $this->esArchivo($contentType, $cuerpo);
        $bytes = strlen($cuerpo);

        // 30-oct F3: por encima de API_CLIENT_RESPUESTA_MAX_MB el cuerpo no viaja en el JSON: queda en un temporal que se
        // baja una sola vez con GET descargar/{token} (si no se pudo escribir, sigue el camino normal).
        $nombreGrande = null;
        $token = null;
        if ($bytes > self::maxBytesRespuesta()) {
            $nombreGrande = $this->nombreArchivoGrande($respuesta->header('Content-Disposition'));
            $token = $this->guardarTemporal($cuerpo, $nombreGrande, $contentType, $bytes);
        }
        $grande = $token !== null;

        return [
            'status' => $respuesta->status(),
            'status_texto' => (string) $psr->getReasonPhrase(),
            'headers' => $headers,
            'cookies' => $cookies,
            // QUITADO 30-oct F3: sin el caso de respuesta grande (reactivar = descomentar)
            // 'body' => $esArchivo ? null : $this->textoSeguro($cuerpo, $contentType),
            // 'body_base64' => $esArchivo ? base64_encode($cuerpo) : null,
            // 'is_file' => $esArchivo,
            // 'filename' => $esArchivo ? $this->nombreArchivo($respuesta->header('Content-Disposition'), $urlPedida) : null,
            'body' => $grande || $esArchivo ? null : $this->textoSeguro($cuerpo, $contentType),
            'body_base64' => !$grande && $esArchivo ? base64_encode($cuerpo) : null,
            'is_file' => $grande || $esArchivo,
            'filename' => $grande ? $nombreGrande : ($esArchivo ? $this->nombreArchivo($respuesta->header('Content-Disposition'), $urlPedida) : null),
            'content_type' => $contentType !== '' ? $this->textoSeguro($contentType) : null,
            'tiempo_ms' => $tiempoMs,
            'tamano_bytes' => $bytes,
            'tamano_kb' => (int) ceil($bytes / 1024),
            // QUITADO 30-oct F3: la URL final salía solo del historial de redirecciones (reactivar = descomentar)
            // 'url_final' => $redirecciones ? (string) end($redirecciones) : $urlPedida,
            'url_final' => $this->urlEfectiva ?: ($redirecciones ? (string) end($redirecciones) : $urlPedida), // 30-oct F3: la URI efectiva de on_stats
            'redirecciones' => count($redirecciones),
            'variables_sin_resolver' => [],
            'variables_origen_pendiente' => [], // 31-oct (fase 6): las de variables_sin_resolver que se llenan ejecutando otro request
            'historial_id' => null,
            'truncado_en_historial' => false,
            'tiempos' => $this->tiempos(), // 30-oct F3: {dns, conexion, ssl, espera, descarga, total} en ms
            'grande' => $grande,           // 30-oct F3
            'descarga_token' => $token,    // 30-oct F3
        ];
    }

    // Texto si el content-type empieza por text/ o contiene json, xml, javascript, graphql o x-www-form-urlencoded; sin
    // content-type decide la codificación. Un cuerpo vacío nunca es archivo.
    private function esArchivo(string $contentType, string $cuerpo): bool
    {
        if ($cuerpo === '') {
            return false;
        }
        $ct = strtolower(trim(explode(';', $contentType)[0]));
        if ($ct === '') {
            return !mb_check_encoding($cuerpo, 'UTF-8');
        }
        if (strpos($ct, 'text/') === 0) {
            return false;
        }
        foreach (['json', 'xml', 'javascript', 'ecmascript', 'graphql', 'x-www-form-urlencoded', 'yaml'] as $texto) {
            if (strpos($ct, $texto) !== false) {
                return false;
            }
        }

        return true;
    }

    private function nombreArchivo(string $disposition, string $url): string
    {
        if ($disposition !== '') {
            if (preg_match("/filename\*\s*=\s*(?:[\w-]+)?''([^;]+)/i", $disposition, $m) && trim($m[1]) !== '') {
                return trim(rawurldecode(trim($m[1])), "\" '");
            }
            if (preg_match('/filename\s*=\s*"([^"]*)"/i', $disposition, $m) && trim($m[1]) !== '') {
                return trim($m[1]);
            }
            if (preg_match('/filename\s*=\s*([^;]+)/i', $disposition, $m) && trim($m[1]) !== '') {
                return trim($m[1], "\" '");
            }
        }
        $ruta = parse_url($url, PHP_URL_PATH);
        $base = is_string($ruta) ? basename($ruta) : '';

        return $base !== '' && $base !== '/' && $base !== '.' ? $base : 'archivo';
    }

    public static function parsearCookie(string $linea): array
    {
        $partes = array_map('trim', explode(';', $linea));
        $primera = (string) array_shift($partes);
        $pos = strpos($primera, '=');
        $cookie = [
            'nombre' => $pos === false ? $primera : substr($primera, 0, $pos),
            'valor' => $pos === false ? '' : substr($primera, $pos + 1),
            'dominio' => null,
            'path' => null,
            'expira' => null,
            'http_only' => false,
            'secure' => false,
            'same_site' => null,
        ];
        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $parte, 2), 2, null);
            switch (strtolower(trim((string) $k))) {
                case 'domain':
                    $cookie['dominio'] = $v;
                    break;
                case 'path':
                    $cookie['path'] = $v;
                    break;
                case 'expires':
                    $cookie['expira'] = $v;
                    break;
                case 'max-age':
                    if ($cookie['expira'] === null && is_numeric($v)) {
                        $cookie['expira'] = gmdate('D, d M Y H:i:s \G\M\T', time() + (int) $v);
                    }
                    break;
                case 'httponly':
                    $cookie['http_only'] = true;
                    break;
                case 'secure':
                    $cookie['secure'] = true;
                    break;
                case 'samesite':
                    $cookie['same_site'] = $v;
                    break;
            }
        }

        return $cookie;
    }

    // UTF-8 válido (convierte según charset del content-type o limpia lo inválido) y sin bytes nulos, que ni el JSON de
    // respuesta ni el jsonb del historial aceptan.
    private function textoSeguro(string $texto, string $contentType = ''): string
    {
        if ($texto === '') {
            return '';
        }
        if (!mb_check_encoding($texto, 'UTF-8')) {
            if ($contentType !== '' && preg_match('/charset=["\']?([\w-]+)/i', $contentType, $m) && strcasecmp($m[1], 'UTF-8') !== 0) {
                try {
                    $convertido = mb_convert_encoding($texto, 'UTF-8', $m[1]);
                    if (is_string($convertido) && mb_check_encoding($convertido, 'UTF-8')) {
                        $texto = $convertido;
                    }
                } catch (Throwable) {
                    // charset desconocido: se limpia abajo
                }
            }
            if (!mb_check_encoding($texto, 'UTF-8')) {
                $texto = mb_scrub($texto, 'UTF-8');
            }
        }

        return str_replace("\0", '', $texto);
    }

    // ------------------------------------------------------------------
    // 30-oct F3: tiempos (on_stats) y respuestas grandes (temporal descargable por token)
    // ------------------------------------------------------------------

    // Guarda el curl_getinfo de cada transferencia (con redirecciones hay una por salto, en orden) y la URI efectiva de
    // la última, que es la url_final.
    private function callbackEstadisticas(): \Closure
    {
        $this->transferencias = [];
        $this->urlEfectiva = null;

        return function (TransferStats $s): void {
            $this->transferencias[] = $s->getHandlerStats();
            $this->urlEfectiva = (string) $s->getEffectiveUri();
        };
    }

    // {dns, conexion, ssl, espera, descarga, total} en ms enteros. cURL da segundos acumulados dentro de cada
    // transferencia (namelookup <= connect <= appconnect <= pretransfer <= starttransfer <= total): los tramos se restan
    // y se suman los de todos los saltos. Sin transferencias (no llegó a enviarse) → null.
    private function tiempos(): ?array
    {
        if (!$this->transferencias) {
            return null;
        }
        $t = ['dns' => 0.0, 'conexion' => 0.0, 'ssl' => 0.0, 'espera' => 0.0, 'descarga' => 0.0, 'total' => 0.0];
        foreach ($this->transferencias as $s) {
            $dns = (float) ($s['namelookup_time'] ?? 0);
            $conexion = (float) ($s['connect_time'] ?? 0);
            $ssl = (float) ($s['appconnect_time'] ?? 0);
            $pre = (float) ($s['pretransfer_time'] ?? 0);
            $primerByte = (float) ($s['starttransfer_time'] ?? 0);
            $total = (float) ($s['total_time'] ?? 0);
            $t['dns'] += $dns;
            $t['conexion'] += max(0, $conexion - $dns);
            $t['ssl'] += $ssl > 0 ? max(0, $ssl - $conexion) : 0;
            $t['espera'] += max(0, $primerByte - $pre);
            $t['descarga'] += max(0, $total - $primerByte);
            $t['total'] += $total;
        }

        return array_map(fn ($v) => (int) round($v * 1000), $t);
    }

    public static function maxBytesRespuesta(): int
    {
        return max(1, (int) env('API_CLIENT_RESPUESTA_MAX_MB', self::RESPUESTA_MAX_MB_DEFECTO)) * 1024 * 1024;
    }

    // storage/app/api-client/tmp (se crea al guardar el primer temporal).
    public static function rutaTemporales(): string
    {
        return storage_path('app' . DIRECTORY_SEPARATOR . 'api-client' . DIRECTORY_SEPARATOR . 'tmp');
    }

    // Ruta del temporal del token sin extensión: <ruta>.bin es el cuerpo y <ruta>.json el nombre, tipo y tamaño.
    public static function rutaTemporal(string $token): string
    {
        return self::rutaTemporales() . DIRECTORY_SEPARATOR . $token;
    }

    public static function tokenValido(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f-]{36}$/', $token);
    }

    // Borra los temporales con más de TEMPORAL_VIDA_SEG (se llama al inicio de cada ejecución). Devuelve cuántos borró.
    public static function limpiarTemporales(?int $vidaSeg = null): int
    {
        $borrados = 0;
        try {
            $dir = self::rutaTemporales();
            if (!is_dir($dir)) {
                return 0;
            }
            $limite = time() - ($vidaSeg ?? self::TEMPORAL_VIDA_SEG);
            $archivos = array_merge(glob($dir . DIRECTORY_SEPARATOR . '*.bin') ?: [], glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: []);
            foreach ($archivos as $archivo) {
                $modificado = @filemtime($archivo);
                if ($modificado !== false && $modificado < $limite && @unlink($archivo)) {
                    $borrados++;
                }
            }
        } catch (Throwable $e) {
            (new Funciones())->logInfo(ApiProxyHelper::class, 'No se pudieron limpiar los temporales: ' . $e->getMessage());
        }

        return $borrados;
    }

    // Escribe <uuid>.bin (el cuerpo tal cual) y <uuid>.json (nombre, tipo, tamaño, usuario). Devuelve el token, o null si
    // no se pudo escribir (entonces la respuesta sigue el camino normal).
    private function guardarTemporal(string $cuerpo, string $nombre, string $contentType, int $bytes): ?string
    {
        try {
            $dir = self::rutaTemporales();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('No se pudo crear ' . $dir);
            }
            $token = (string) Str::uuid();
            $base = self::rutaTemporal($token);
            if (@file_put_contents($base . '.bin', $cuerpo) !== $bytes) {
                @unlink($base . '.bin');
                throw new \RuntimeException('No se pudo escribir ' . $base . '.bin');
            }
            $u = auth('api')->user();
            file_put_contents($base . '.json', json_encode([
                'filename' => $nombre,
                'content_type' => $contentType,
                'tamano_bytes' => $bytes,
                'usuario_id' => $u ? (int) $u->id : null,
                'creado' => date('c'),
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

            return $token;
        } catch (Throwable $e) {
            $this->log->logError(ApiProxyHelper::class, 'No se pudo guardar la respuesta grande como temporal (viaja en el JSON)', $e);

            return null;
        }
    }

    // El nombre del Content-Disposition; sin él, respuesta.bin (la URL de respaldo solo aporta ese nombre).
    private function nombreArchivoGrande(string $disposition): string
    {
        return $this->nombreArchivo($disposition, 'http://api-client/respuesta.bin');
    }

    // "25", "20.5" (para el texto del historial).
    private static function textoMegas(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------
    // Errores
    // ------------------------------------------------------------------

    private function tipoError(Throwable $e, string $uid): string
    {
        if ($e instanceof ApiCanceladoException) {
            return 'cancelado';
        }
        $m = strtolower($e->getMessage());
        if (strpos($m, 'callback aborted') !== false || Cache::has(self::claveCancelar($uid))) {
            return 'cancelado';
        }
        if (strpos($m, 'timed out') !== false || strpos($m, 'timeout') !== false) {
            return 'timeout';
        }
        if (strpos($m, 'ssl') !== false || strpos($m, 'certificate') !== false) {
            return 'ssl';
        }
        if ($e instanceof ConnectionException || strpos($m, 'curl error') !== false) {
            return 'conexion';
        }

        return 'proxy';
    }

    private function resultadoError(string $tipo, string $mensaje, int $tiempoMs, ?int $historialId): array
    {
        return [
            'status' => 'error',
            'message' => $tipo === 'validacion' ? $mensaje : self::MENSAJES_CORTOS[$tipo],
            'data' => [
                'error' => true,
                'error_tipo' => $tipo,
                'mensaje' => $mensaje,
                'tiempo_ms' => $tiempoMs,
                'historial_id' => $historialId,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Historial
    // ------------------------------------------------------------------

    // Siempre en su propio try/catch: si falla, la respuesta igual vuelve al usuario (historial_id null).
    // Devuelve [id|null, truncado]. El body de la respuesta se recorta aquí a max_kb; la función recibe además
    // respuesta_truncada y respuesta_tamano_kb (tamaño real) para marcar la fila.
    // 30-oct F3: también tiempos, url_final y respuesta_cookies; una respuesta grande deja solo "[respuesta de N MB: descarga temporal]".
    private function registrarHistorial(array $p, string $url, ?array $data, ?string $errorTipo, int $tiempoMs, ?int $usuarioId): array
    {
        $maxKb = max(1, (int) env('API_CLIENT_HISTORIAL_MAX_KB', 256));
        $maxBytes = $maxKb * 1024;
        $truncado = false;

        if ($data === null) {
            $respuestaBody = null;
            $respuestaHeaders = null;
            $status = 0;
            $contentType = null;
            $tamanoKb = 0;
        } else {
            $status = (int) $data['status'];
            $respuestaHeaders = $data['headers'];
            $contentType = $data['content_type'];
            $tamanoKb = (int) $data['tamano_kb'];
            // QUITADO 30-oct F3: sin el caso de respuesta grande (reactivar = descomentar)
            // if ($data['is_file']) {
            if (!empty($data['grande'])) {
                $respuestaBody = '[respuesta de ' . self::textoMegas((int) $data['tamano_bytes']) . ' MB: descarga temporal]'; // 30-oct F3
            } elseif ($data['is_file']) {
                $respuestaBody = '[archivo: ' . $data['filename'] . ', ' . $tamanoKb . ' KB]';
            } else {
                $respuestaBody = (string) ($data['body'] ?? '');
                if (strlen($respuestaBody) > $maxBytes) {
                    $respuestaBody = mb_strcut($respuestaBody, 0, $maxBytes);
                    $truncado = true;
                }
            }
        }

        $body = $p['body'];
        if ($p['body_tipo'] === 'binary') {
            $info = json_decode((string) $body, true);
            $body = '[archivo: ' . (is_array($info) && !empty($info['nombre_archivo']) ? $info['nombre_archivo'] : 'binario') . ']';
        } elseif ($body !== null && strlen($body) > $maxBytes) {
            $body = mb_strcut($body, 0, $maxBytes);
        }

        try {
            $fila = DB::selectOne('SELECT crm.fn_api_historial_registrar(?::jsonb) AS id', [json_encode([
                'usuario_id' => $usuarioId,
                'api_requests_id' => $p['api_requests_id'],
                'api_colecciones_id' => $p['api_colecciones_id'],
                'nombre' => $p['nombre'],
                'metodo' => $p['metodo'],
                'url' => $url,
                'headers' => $p['headers'],
                'params' => $p['params'],
                'body_tipo' => $p['body_tipo'],
                'body_subtipo' => $p['body_subtipo'],
                'body' => $body === null ? null : $this->textoSeguro($body),
                'auth_tipo' => $p['auth_tipo'],
                'auth_data' => $p['auth_data'],
                'respuesta_status' => $status,
                'respuesta_headers' => $respuestaHeaders,
                'respuesta_body' => $respuestaBody === null ? null : $this->textoSeguro($respuestaBody),
                'respuesta_content_type' => $contentType,
                'respuesta_tiempo_ms' => $tiempoMs,
                'respuesta_tamano_kb' => $tamanoKb,
                'respuesta_truncada' => $truncado,
                'error_tipo' => $errorTipo,
                'max_kb' => $maxKb,
                // 30-oct F3: desglose de tiempos, URL final y cookies parseadas (columnas tiempos, url_final, respuesta_cookies)
                'tiempos' => $data['tiempos'] ?? null,
                'url_final' => $data['url_final'] ?? null,
                'respuesta_cookies' => $data['cookies'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);

            return [$fila && $fila->id !== null ? (int) $fila->id : null, $truncado];
        } catch (Throwable $e) {
            $this->log->logError(ApiProxyHelper::class, 'No se pudo guardar el historial', $e);

            return [null, $truncado];
        }
    }
}
