<?php

namespace App\Http\Resources\api;

use Illuminate\Support\Str;

// 30-oct F2: conversión entre el formato Postman Collection v2.1 y el árbol normalizado que entienden
// crm.fn_api_coleccion_importar (desdeV21 / desdeNativo) y crm.fn_api_coleccion_exportar (aV21). Lo que Postman tiene y
// este módulo no (scripts, protocolProfileBehavior, variables de ruta, auth digest/awsv4/...) se anota en no_soportado y
// se ignora; las credenciales enmascaradas (***) viajan tal cual.
class ApiPostmanHelper
{
    public const SCHEMA_V21 = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
    public const METODOS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    public const BODY_SUBTIPOS = ['json', 'xml', 'text', 'javascript', 'html'];
    private const PATRON_NOMBRE_VARIABLE = '/^[A-Za-z0-9_][A-Za-z0-9_.\-]*$/';
    private const TEXTO_STATUS = [
        100 => 'Continue', 200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content', 301 => 'Moved Permanently',
        302 => 'Found', 304 => 'Not Modified', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout',
    ];

    // Acumuladores de una conversión (se reinician en cada desdeV21)
    private array $noSoportado = [];
    private int $scripts = 0;
    private int $comportamientos = 0;
    private int $variablesRuta = 0;

    // ------------------------------------------------------------------
    // Detección de formato
    // ------------------------------------------------------------------

    // Colección Postman v2.x: info + item[]
    public static function esPostman(array $json): bool
    {
        return is_array($json['info'] ?? null) && is_array($json['item'] ?? null);
    }

    // Exportación nativa de este módulo (fn_api_coleccion_exportar): coleccion + carpetas/requests
    public static function esNativo(array $json): bool
    {
        return is_array($json['coleccion'] ?? null) && (is_array($json['carpetas'] ?? null) || is_array($json['requests'] ?? null));
    }

    // Conteo del árbol normalizado: {carpetas, requests, ejemplos, no_soportado}
    public static function resumen(array $normalizado): array
    {
        $totales = ['carpetas' => 0, 'requests' => 0, 'ejemplos' => 0];
        self::contarNodo($normalizado, $totales);
        $totales['no_soportado'] = array_values(is_array($normalizado['no_soportado'] ?? null) ? $normalizado['no_soportado'] : []);

        return $totales;
    }

    private static function contarNodo(array $nodo, array &$totales): void
    {
        foreach (is_array($nodo['requests'] ?? null) ? $nodo['requests'] : [] as $r) {
            $totales['requests']++;
            $totales['ejemplos'] += is_array($r['ejemplos'] ?? null) ? count($r['ejemplos']) : 0;
        }
        foreach (is_array($nodo['carpetas'] ?? null) ? $nodo['carpetas'] : [] as $k) {
            $totales['carpetas']++;
            if (is_array($k)) {
                self::contarNodo($k, $totales);
            }
        }
    }

    // ------------------------------------------------------------------
    // Postman v2.1 → normalizado
    // ------------------------------------------------------------------

    // Devuelve {nombre, descripcion, auth_tipo, auth_data, headers, variables, carpetas, requests, no_soportado}.
    public static function desdeV21(array $json): array
    {
        return (new self())->convertirV21($json);
    }

    private function convertirV21(array $json): array
    {
        $this->noSoportado = [];
        $this->scripts = 0;
        $this->comportamientos = 0;
        $this->variablesRuta = 0;

        $info = is_array($json['info'] ?? null) ? $json['info'] : [];
        [$authTipo, $authData] = $this->authDesdeV21($json['auth'] ?? null, false, 'colección');
        $this->contarScripts($json['event'] ?? null);
        if (isset($json['protocolProfileBehavior'])) {
            $this->comportamientos++;
        }
        $nodo = $this->nodoDesdeItems(is_array($json['item'] ?? null) ? $json['item'] : []);

        $generales = [];
        if ($this->scripts > 0) {
            $generales[] = 'Se ignoraron ' . $this->scripts . ' script(s) de pre-request/tests (event).';
        }
        if ($this->comportamientos > 0) {
            $generales[] = 'Se ignoró protocolProfileBehavior en ' . $this->comportamientos . ' elemento(s).';
        }
        if ($this->variablesRuta > 0) {
            $generales[] = 'Variables de ruta (url.variable) en ' . $this->variablesRuta . ' request(s): quedan como :nombre en la URL.';
        }

        return [
            'nombre' => trim($this->texto($info['name'] ?? '')),
            'descripcion' => $this->descripcion($info['description'] ?? null),
            'auth_tipo' => $authTipo,
            'auth_data' => $authData,
            'headers' => [],
            'variables' => $this->variablesDesdeV21($json['variable'] ?? null),
            'carpetas' => $nodo['carpetas'],
            'requests' => $nodo['requests'],
            'no_soportado' => array_values(array_unique(array_merge($generales, $this->noSoportado))),
        ];
    }

    // item[] recursivo: con item[] es carpeta, con request es request; lo demás se anota y se ignora.
    private function nodoDesdeItems(array $items): array
    {
        $nodo = ['carpetas' => [], 'requests' => []];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nombre = trim($this->texto($item['name'] ?? ''));
            $this->contarScripts($item['event'] ?? null);
            if (isset($item['protocolProfileBehavior'])) {
                $this->comportamientos++;
            }
            if (is_array($item['item'] ?? null)) {
                $tipoAuth = is_array($item['auth'] ?? null) ? strtolower(trim($this->texto($item['auth']['type'] ?? ''))) : '';
                if ($tipoAuth !== '' && $tipoAuth !== 'inherit') {
                    $this->noSoportado[] = 'Autenticación de la carpeta «' . ($nombre !== '' ? $nombre : 'Carpeta') . '»: no soportada (se ignora).';
                }
                $hijos = $this->nodoDesdeItems($item['item']);
                $nodo['carpetas'][] = [
                    'nombre' => $nombre !== '' ? $nombre : 'Carpeta',
                    'descripcion' => $this->descripcion($item['description'] ?? null),
                    'carpetas' => $hijos['carpetas'],
                    'requests' => $hijos['requests'],
                ];
                continue;
            }
            if (isset($item['request'])) {
                $nodo['requests'][] = $this->requestDesdeV21($item, $nombre !== '' ? $nombre : 'Sin nombre');
                continue;
            }
            $this->noSoportado[] = 'Elemento «' . ($nombre !== '' ? $nombre : 'sin nombre') . '» sin request ni carpeta: se ignoró.';
        }

        return $nodo;
    }

    private function requestDesdeV21(array $item, string $nombre): array
    {
        $req = $item['request'];
        if (is_string($req)) {
            $req = ['method' => 'GET', 'url' => $req];
        }
        if (!is_array($req)) {
            $req = [];
        }
        $base = $this->requestBaseDesdeV21($req, $nombre);
        [$authTipo, $authData] = $this->authDesdeV21($req['auth'] ?? null, true, 'request «' . $nombre . '»');

        $ejemplos = [];
        foreach (is_array($item['response'] ?? null) ? $item['response'] : [] as $respuesta) {
            if (is_array($respuesta)) {
                $ejemplos[] = $this->ejemploDesdeV21($respuesta, $base, $nombre);
            }
        }

        return $base + [
            'nombre' => $nombre,
            'auth_tipo' => $authTipo,
            'auth_data' => $authData,
            'opciones' => [],
            'ejemplos' => $ejemplos,
        ];
    }

    // Lo común a un request y al originalRequest de un ejemplo: metodo, url (sin query), params, headers, body_* y descripcion.
    private function requestBaseDesdeV21(array $req, string $nombre): array
    {
        $metodo = strtoupper(trim($this->texto($req['method'] ?? 'GET')));
        if ($metodo === '') {
            $metodo = 'GET';
        }
        if (!in_array($metodo, self::METODOS, true)) {
            $this->noSoportado[] = 'Método «' . $metodo . '» del request «' . $nombre . '»: no soportado (se usó GET).';
            $metodo = 'GET';
        }
        [$url, $params] = $this->urlDesdeV21($req['url'] ?? '');
        [$bodyTipo, $bodySubtipo, $body] = $this->bodyDesdeV21($req['body'] ?? null);

        return [
            'metodo' => $metodo,
            'url' => $url,
            'params' => $params,
            'headers' => $this->filasDesdeV21($req['header'] ?? null),
            'body_tipo' => $bodyTipo,
            'body_subtipo' => $bodySubtipo,
            'body' => $body,
            'descripcion' => $this->descripcion($req['description'] ?? null),
        ];
    }

    // url string o {raw, protocol, host[], port, path[], query[], variable[]} → [url sin la query, params]. Si viene query[]
    // manda sobre la query de raw (trae disabled/description); si no, se parsea la de raw.
    private function urlDesdeV21($url): array
    {
        $params = null;
        if (is_array($url)) {
            if (is_array($url['variable'] ?? null) && $url['variable']) {
                $this->variablesRuta++;
            }
            $raw = trim($this->texto($url['raw'] ?? ''));
            if ($raw === '') {
                $raw = $this->armarRawDesdeV21($url);
            }
            if (is_array($url['query'] ?? null)) {
                $params = $this->filasDesdeV21($url['query']);
            }
        } else {
            $raw = trim($this->texto($url));
        }

        $pos = strpos($raw, '?');
        $sinQuery = $pos === false ? $raw : substr($raw, 0, $pos);
        $queryTexto = $pos === false ? '' : substr($raw, $pos + 1);
        $hash = strpos($queryTexto, '#');
        if ($hash !== false) {
            $queryTexto = substr($queryTexto, 0, $hash);
        }
        if ($params === null) {
            $params = $this->paramsDesdeQuery($queryTexto);
        }

        return [$sinQuery, $params];
    }

    private function armarRawDesdeV21(array $url): string
    {
        $host = $url['host'] ?? '';
        $host = is_array($host) ? implode('.', array_map(fn ($h) => $this->texto($h), $host)) : $this->texto($host);
        $path = $url['path'] ?? '';
        $path = is_array($path)
            ? implode('/', array_map(fn ($p) => is_array($p) ? $this->texto($p['value'] ?? '') : $this->texto($p), $path))
            : $this->texto($path);
        $protocolo = trim($this->texto($url['protocol'] ?? ''));
        $puerto = trim($this->texto($url['port'] ?? ''));

        $raw = ($protocolo !== '' ? $protocolo . '://' : '') . $host . ($puerto !== '' ? ':' . $puerto : '');
        if ($path !== '') {
            $raw .= '/' . ltrim($path, '/');
        }

        return $raw;
    }

    // "a=1&b=2" → filas (sin decodificar: Postman guarda la query tal como se escribió)
    private function paramsDesdeQuery(string $query): array
    {
        $filas = [];
        foreach (explode('&', $query) as $trozo) {
            if ($trozo === '') {
                continue;
            }
            $kv = explode('=', $trozo, 2);
            $filas[] = ['key' => $kv[0], 'value' => $kv[1] ?? '', 'activo' => true];
        }

        return $filas;
    }

    // header[] / query[] / urlencoded[] → filas {key, value, activo, descripcion?}. Acepta "Clave: Valor" como texto.
    private function filasDesdeV21($lista): array
    {
        $filas = [];
        foreach (is_array($lista) ? $lista : [] as $item) {
            if (is_string($item)) {
                $kv = explode(':', $item, 2);
                $item = ['key' => $kv[0], 'value' => isset($kv[1]) ? ltrim($kv[1]) : ''];
            }
            if (!is_array($item)) {
                continue;
            }
            $key = trim($this->texto($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $fila = ['key' => $key, 'value' => $this->texto($item['value'] ?? ''), 'activo' => ($item['disabled'] ?? false) !== true];
            $descripcion = $this->descripcion($item['description'] ?? null);
            if ($descripcion !== null) {
                $fila['descripcion'] = $descripcion;
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    // body.mode → [body_tipo, body_subtipo, body] con el body serializado como lo guarda api_requests.body.
    private function bodyDesdeV21($body): array
    {
        if (!is_array($body)) {
            return ['none', null, null];
        }
        $modo = strtolower(trim($this->texto($body['mode'] ?? '')));
        switch ($modo) {
            case 'raw':
                $raw = $this->texto($body['raw'] ?? '');
                $lenguaje = strtolower(trim($this->texto($body['options']['raw']['language'] ?? '')));
                if ($lenguaje === 'js') {
                    $lenguaje = 'javascript';
                }
                if (!in_array($lenguaje, self::BODY_SUBTIPOS, true)) {
                    $lenguaje = $this->pareceJson($raw) ? 'json' : 'text';
                }

                return ['raw', $lenguaje, $raw];

            case 'urlencoded':
                return ['urlencoded', null, $this->json($this->filasDesdeV21($body['urlencoded'] ?? null))];

            case 'formdata':
                $filas = [];
                foreach (is_array($body['formdata'] ?? null) ? $body['formdata'] : [] as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $key = trim($this->texto($f['key'] ?? ''));
                    $esArchivo = strtolower(trim($this->texto($f['type'] ?? 'text'))) === 'file';
                    if ($key === '' && !$esArchivo) {
                        continue;
                    }
                    $fila = [
                        'key' => $key,
                        'value' => $esArchivo ? '' : $this->texto($f['value'] ?? ''),
                        'activo' => ($f['disabled'] ?? false) !== true,
                        'tipo' => $esArchivo ? 'file' : 'text',
                    ];
                    if ($esArchivo) {
                        $fila['nombre_archivo'] = $this->nombreArchivo($f['src'] ?? null);
                    }
                    $descripcion = $this->descripcion($f['description'] ?? null);
                    if ($descripcion !== null) {
                        $fila['descripcion'] = $descripcion;
                    }
                    $filas[] = $fila;
                }

                return ['form-data', null, $this->json($filas)];

            case 'file':
                $src = is_array($body['file'] ?? null) ? ($body['file']['src'] ?? null) : ($body['file'] ?? null);

                return ['binary', null, $this->json(['nombre_archivo' => $this->nombreArchivo($src)])];

            case 'graphql':
                $g = is_array($body['graphql'] ?? null) ? $body['graphql'] : [];
                $variables = $g['variables'] ?? '';
                if (!is_string($variables)) {
                    $variables = $variables === null ? '' : $this->json($variables, true);
                }

                return ['graphql', null, $this->json(['query' => $this->texto($g['query'] ?? ''), 'variables' => $variables])];

            default:
                return ['none', null, null];
        }
    }

    // response[] → ejemplo {nombre, request_snapshot, respuesta_status, respuesta_headers, respuesta_body, respuesta_content_type, respuesta_tiempo_ms}
    private function ejemploDesdeV21(array $respuesta, array $baseRequest, string $nombreRequest): array
    {
        $original = $respuesta['originalRequest'] ?? null;
        if (is_string($original)) {
            $original = ['method' => 'GET', 'url' => $original];
        }
        $snapshot = is_array($original) ? $this->requestBaseDesdeV21($original, $nombreRequest) : $baseRequest;
        unset($snapshot['descripcion']);

        $headers = [];
        $contentType = null;
        foreach (is_array($respuesta['header'] ?? null) ? $respuesta['header'] : [] as $h) {
            if (is_string($h)) {
                $kv = explode(':', $h, 2);
                $h = ['key' => $kv[0], 'value' => isset($kv[1]) ? ltrim($kv[1]) : ''];
            }
            if (!is_array($h)) {
                continue;
            }
            $key = trim($this->texto($h['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $valor = $this->texto($h['value'] ?? '');
            $headers[$key] = isset($headers[$key]) ? $headers[$key] . ', ' . $valor : $valor;
            if (strcasecmp($key, 'Content-Type') === 0) {
                $contentType = $valor;
            }
        }
        if ($contentType === null) {
            $contentType = $this->contentTypeDesdePreview($this->texto($respuesta['_postman_previewlanguage'] ?? ''));
        }
        $nombre = trim($this->texto($respuesta['name'] ?? ''));

        return [
            'nombre' => $nombre !== '' ? $nombre : 'Ejemplo',
            'request_snapshot' => $snapshot,
            'respuesta_status' => isset($respuesta['code']) && is_numeric($respuesta['code']) ? (int) $respuesta['code'] : null,
            'respuesta_headers' => $headers,
            'respuesta_body' => array_key_exists('body', $respuesta) && $respuesta['body'] !== null ? $this->texto($respuesta['body']) : null,
            'respuesta_content_type' => $contentType,
            'respuesta_tiempo_ms' => isset($respuesta['responseTime']) && is_numeric($respuesta['responseTime']) ? (int) round((float) $respuesta['responseTime']) : null,
        ];
    }

    // auth de Postman → [auth_tipo, auth_data]. Sin auth: la colección queda en none y el request hereda.
    private function authDesdeV21($auth, bool $esRequest, string $contexto): array
    {
        $porDefecto = [$esRequest ? 'heredar' : 'none', []];
        if (!is_array($auth) || !isset($auth['type'])) {
            return $porDefecto;
        }
        $tipo = strtolower(trim($this->texto($auth['type'])));
        $p = $this->paramsAuth($auth[$tipo] ?? null);
        switch ($tipo) {
            case '':
            case 'inherit':
                return $porDefecto;
            case 'noauth':
                return ['none', []];
            case 'bearer':
                return ['bearer', ['token' => $this->texto($p['token'] ?? '')]];
            case 'basic':
                return ['basic', ['username' => $this->texto($p['username'] ?? ''), 'password' => $this->texto($p['password'] ?? '')]];
            case 'apikey':
                return ['api-key', [
                    'key' => $this->texto($p['key'] ?? ''),
                    'value' => $this->texto($p['value'] ?? ''),
                    'in' => strtolower($this->texto($p['in'] ?? 'header')) === 'query' ? 'query' : 'header',
                ]];
            case 'oauth2':
                $data = [];
                foreach (['accessToken' => 'access_token', 'accessTokenUrl' => 'token_url', 'clientId' => 'client_id', 'clientSecret' => 'client_secret',
                    'scope' => 'scope', 'username' => 'username', 'password' => 'password'] as $dePostman => $nuestro) {
                    if (isset($p[$dePostman]) && $this->texto($p[$dePostman]) !== '') {
                        $data[$nuestro] = $this->texto($p[$dePostman]);
                    }
                }
                $grant = strtolower($this->texto($p['grant_type'] ?? ''));
                if ($grant === 'client_credentials') {
                    $data['grant_type'] = 'client_credentials';
                } elseif (in_array($grant, ['password_credentials', 'password'], true)) {
                    $data['grant_type'] = 'password';
                }

                return ['oauth2', $data];
            default:
                $this->noSoportado[] = 'Autenticación «' . $tipo . '» de la ' . $contexto . ': no soportada (quedó sin autenticación).';

                return ['none', []];
        }
    }

    // Los parámetros de auth vienen como lista [{key, value, type}] (v2.1) o como objeto {clave: valor} (v2.0).
    private function paramsAuth($lista): array
    {
        if (!is_array($lista)) {
            return [];
        }
        $esLista = array_keys($lista) === range(0, count($lista) - 1);
        if (!$esLista) {
            return $lista;
        }
        $p = [];
        foreach ($lista as $item) {
            if (is_array($item) && isset($item['key'])) {
                $p[(string) $item['key']] = $item['value'] ?? '';
            }
        }

        return $p;
    }

    // variable[] {key, value, disabled, description} → variables de la colección; nombres inválidos o repetidos se anotan.
    private function variablesDesdeV21($lista): array
    {
        $variables = [];
        $vistos = [];
        foreach (is_array($lista) ? $lista : [] as $v) {
            if (!is_array($v)) {
                continue;
            }
            $nombre = trim($this->texto($v['key'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            if (!preg_match(self::PATRON_NOMBRE_VARIABLE, $nombre)) {
                $this->noSoportado[] = 'Variable «' . $nombre . '» omitida: el nombre solo admite letras, números, guion, guion bajo y punto.';
                continue;
            }
            $clave = strtolower($nombre);
            if (isset($vistos[$clave])) {
                $this->noSoportado[] = 'Variable «' . $nombre . '» repetida: se conservó la primera.';
                continue;
            }
            $vistos[$clave] = true;
            $variables[] = [
                'nombre' => $nombre,
                'valor' => $this->texto($v['value'] ?? ''),
                'descripcion' => $this->descripcion($v['description'] ?? null),
                'estado' => ($v['disabled'] ?? false) !== true,
            ];
        }

        return $variables;
    }

    private function contarScripts($eventos): void
    {
        if (is_array($eventos)) {
            $this->scripts += count(array_filter($eventos, 'is_array'));
        }
    }

    // ------------------------------------------------------------------
    // Nativo (exportación de este módulo) → normalizado
    // ------------------------------------------------------------------

    public static function desdeNativo(array $json): array
    {
        $c = $json['coleccion'];

        return [
            'nombre' => trim(self::aTexto($c['nombre'] ?? '')),
            'descripcion' => isset($c['descripcion']) && is_scalar($c['descripcion']) ? (string) $c['descripcion'] : null,
            'color' => isset($c['color']) && is_scalar($c['color']) ? (string) $c['color'] : null,
            'auth_tipo' => isset($c['auth_tipo']) && is_scalar($c['auth_tipo']) ? (string) $c['auth_tipo'] : 'none',
            'auth_data' => is_array($c['auth_data'] ?? null) ? $c['auth_data'] : [],
            'headers' => is_array($c['headers'] ?? null) ? array_values($c['headers']) : [],
            'variables' => is_array($c['variables'] ?? null) ? array_values($c['variables']) : [],
            'carpetas' => self::carpetasNativas($json['carpetas'] ?? null),
            'requests' => self::requestsNativos($json['requests'] ?? null),
            'no_soportado' => [],
        ];
    }

    private static function carpetasNativas($lista): array
    {
        $carpetas = [];
        foreach (is_array($lista) ? $lista : [] as $k) {
            if (!is_array($k)) {
                continue;
            }
            $carpetas[] = [
                'nombre' => trim(self::aTexto($k['nombre'] ?? '')),
                'descripcion' => isset($k['descripcion']) && is_scalar($k['descripcion']) ? (string) $k['descripcion'] : null,
                'carpetas' => self::carpetasNativas($k['carpetas'] ?? null),
                'requests' => self::requestsNativos($k['requests'] ?? null),
            ];
        }

        return $carpetas;
    }

    private static function requestsNativos($lista): array
    {
        $campos = array_flip(['nombre', 'metodo', 'url', 'params', 'headers', 'body_tipo', 'body_subtipo', 'body', 'auth_tipo', 'auth_data', 'opciones', 'descripcion']);
        $camposEjemplo = array_flip(['nombre', 'request_snapshot', 'respuesta_status', 'respuesta_headers', 'respuesta_body', 'respuesta_content_type', 'respuesta_tiempo_ms']);
        $requests = [];
        foreach (is_array($lista) ? $lista : [] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $request = array_intersect_key($r, $campos);
            $request['ejemplos'] = [];
            foreach (is_array($r['ejemplos'] ?? null) ? $r['ejemplos'] : [] as $e) {
                if (is_array($e)) {
                    $request['ejemplos'][] = array_intersect_key($e, $camposEjemplo);
                }
            }
            $requests[] = $request;
        }

        return $requests;
    }

    // ------------------------------------------------------------------
    // Exportado (fn_api_coleccion_exportar) → Postman v2.1
    // ------------------------------------------------------------------

    // Los headers de la colección no tienen equivalente en Postman: solo viajan en el formato nativo.
    public static function aV21(array $exportado): array
    {
        $c = is_array($exportado['coleccion'] ?? null) ? $exportado['coleccion'] : [];
        $nombre = trim(self::aTexto($c['nombre'] ?? ''));

        $info = ['_postman_id' => (string) Str::uuid(), 'name' => $nombre !== '' ? $nombre : 'Colección'];
        if (isset($c['descripcion']) && trim(self::aTexto($c['descripcion'])) !== '') {
            $info['description'] = (string) $c['descripcion'];
        }
        $info['schema'] = self::SCHEMA_V21;

        $salida = ['info' => $info, 'item' => self::itemsAV21($exportado)];
        $auth = self::authAV21(self::aTexto($c['auth_tipo'] ?? 'none'), $c['auth_data'] ?? []);
        if ($auth !== null) {
            $salida['auth'] = $auth;
        }
        $salida['variable'] = [];
        foreach (is_array($c['variables'] ?? null) ? $c['variables'] : [] as $v) {
            if (!is_array($v) || trim(self::aTexto($v['nombre'] ?? '')) === '') {
                continue;
            }
            $variable = ['key' => trim(self::aTexto($v['nombre'])), 'value' => self::aTexto($v['valor'] ?? ''), 'type' => 'string'];
            if (($v['estado'] ?? true) === false) {
                $variable['disabled'] = true;
            }
            if (isset($v['descripcion']) && trim(self::aTexto($v['descripcion'])) !== '') {
                $variable['description'] = (string) $v['descripcion'];
            }
            $salida['variable'][] = $variable;
        }

        return $salida;
    }

    // Carpetas (con item[]) primero y luego los requests, como ordena el árbol.
    private static function itemsAV21(array $nodo): array
    {
        $items = [];
        foreach (is_array($nodo['carpetas'] ?? null) ? $nodo['carpetas'] : [] as $k) {
            if (!is_array($k)) {
                continue;
            }
            $carpeta = ['name' => trim(self::aTexto($k['nombre'] ?? '')) ?: 'Carpeta'];
            if (isset($k['descripcion']) && trim(self::aTexto($k['descripcion'])) !== '') {
                $carpeta['description'] = (string) $k['descripcion'];
            }
            $carpeta['item'] = self::itemsAV21($k);
            $items[] = $carpeta;
        }
        foreach (is_array($nodo['requests'] ?? null) ? $nodo['requests'] : [] as $r) {
            if (is_array($r)) {
                $items[] = self::requestAV21($r);
            }
        }

        return $items;
    }

    private static function requestAV21(array $r): array
    {
        $item = [
            'name' => trim(self::aTexto($r['nombre'] ?? '')) ?: 'Sin nombre',
            'request' => self::requestCuerpoAV21($r, true),
            'response' => [],
        ];
        foreach (is_array($r['ejemplos'] ?? null) ? $r['ejemplos'] : [] as $e) {
            if (is_array($e)) {
                $item['response'][] = self::ejemploAV21($e, $r);
            }
        }

        return $item;
    }

    // {method, header[], body?, url{}, auth?, description?}. Con $conAuth false (originalRequest) no va auth.
    private static function requestCuerpoAV21(array $r, bool $conAuth): array
    {
        $req = [
            'method' => strtoupper(self::aTexto($r['metodo'] ?? 'GET')) ?: 'GET',
            'header' => self::filasAV21(self::filasDesdeLista($r['headers'] ?? null)),
        ];
        $body = self::bodyAV21($r);
        if ($body !== null) {
            $req['body'] = $body;
        }
        $req['url'] = self::urlAV21(self::aTexto($r['url'] ?? ''), self::filasDesdeLista($r['params'] ?? null));
        if ($conAuth) {
            $auth = self::authAV21(self::aTexto($r['auth_tipo'] ?? 'none'), $r['auth_data'] ?? []);
            if ($auth !== null) {
                $req['auth'] = $auth;
            }
        }
        if (isset($r['descripcion']) && trim(self::aTexto($r['descripcion'])) !== '') {
            $req['description'] = (string) $r['descripcion'];
        }

        return $req;
    }

    // Filas {key, value, activo, descripcion?} → [{key, value, type: 'text', disabled?, description?}]
    private static function filasAV21(array $filas): array
    {
        $salida = [];
        foreach ($filas as $f) {
            if (!is_array($f) || trim(self::aTexto($f['key'] ?? '')) === '') {
                continue;
            }
            $item = ['key' => trim(self::aTexto($f['key'])), 'value' => self::aTexto($f['value'] ?? ''), 'type' => 'text'];
            if (($f['activo'] ?? true) === false) {
                $item['disabled'] = true;
            }
            if (isset($f['descripcion']) && trim(self::aTexto($f['descripcion'])) !== '') {
                $item['description'] = (string) $f['descripcion'];
            }
            $salida[] = $item;
        }

        return $salida;
    }

    // url + params → {raw, protocol?, host[], port?, path[], query[]}. La url guardada ya trae la query sincronizada con los
    // params activos; si no la trae (p. ej. importada), raw la suma.
    private static function urlAV21(string $url, array $params): array
    {
        $url = trim($url);
        $activos = [];
        $query = [];
        foreach ($params as $p) {
            if (!is_array($p) || trim(self::aTexto($p['key'] ?? '')) === '') {
                continue;
            }
            $key = trim(self::aTexto($p['key']));
            $valor = self::aTexto($p['value'] ?? '');
            $q = ['key' => $key, 'value' => $valor];
            if (($p['activo'] ?? true) === false) {
                $q['disabled'] = true;
            } else {
                $activos[] = $key . '=' . $valor;
            }
            if (isset($p['descripcion']) && trim(self::aTexto($p['descripcion'])) !== '') {
                $q['description'] = (string) $p['descripcion'];
            }
            $query[] = $q;
        }
        $raw = $url;
        if ($activos && strpos($url, '?') === false) {
            $raw = $url . '?' . implode('&', $activos);
        }

        $base = explode('?', $url, 2)[0];
        $base = explode('#', $base, 2)[0];
        $salida = ['raw' => $raw];
        $resto = $base;
        if (preg_match('#^([a-z][a-z0-9+.\-]*)://(.*)$#i', $base, $m)) {
            $salida['protocol'] = strtolower($m[1]);
            $resto = $m[2];
        }
        $pos = strpos($resto, '/');
        $hostPuerto = $pos === false ? $resto : substr($resto, 0, $pos);
        $ruta = $pos === false ? '' : substr($resto, $pos + 1);
        if (preg_match('/^(.+):(\d+|\{\{[^}]*\}\})$/', $hostPuerto, $m)) {
            $hostPuerto = $m[1];
            $salida['port'] = $m[2];
        }
        if ($hostPuerto !== '') {
            $salida['host'] = strpos($hostPuerto, '{{') !== false ? [$hostPuerto] : explode('.', $hostPuerto);
        }
        $segmentos = $ruta === '' ? [] : array_values(array_filter(explode('/', $ruta), fn ($s) => $s !== ''));
        if ($segmentos) {
            $salida['path'] = $segmentos;
        }
        if ($query) {
            $salida['query'] = $query;
        }

        return $salida;
    }

    private static function bodyAV21(array $r): ?array
    {
        $tipo = self::aTexto($r['body_tipo'] ?? 'none');
        $body = isset($r['body']) && $r['body'] !== null ? self::aTexto($r['body']) : '';
        switch ($tipo) {
            case 'raw':
                $subtipo = self::aTexto($r['body_subtipo'] ?? '');

                return ['mode' => 'raw', 'raw' => $body, 'options' => ['raw' => ['language' => in_array($subtipo, self::BODY_SUBTIPOS, true) ? $subtipo : 'text']]];

            case 'urlencoded':
                return ['mode' => 'urlencoded', 'urlencoded' => self::filasAV21(self::filasDesdeJson($body))];

            case 'form-data':
                $partes = [];
                foreach (self::filasDesdeJson($body) as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $key = trim(self::aTexto($f['key'] ?? ''));
                    $esArchivo = ($f['tipo'] ?? 'text') === 'file';
                    if ($key === '' && !$esArchivo) {
                        continue;
                    }
                    $parte = $esArchivo
                        ? ['key' => $key, 'type' => 'file', 'src' => self::aTexto($f['nombre_archivo'] ?? '')]
                        : ['key' => $key, 'value' => self::aTexto($f['value'] ?? ''), 'type' => 'text'];
                    if (($f['activo'] ?? true) === false) {
                        $parte['disabled'] = true;
                    }
                    if (isset($f['descripcion']) && trim(self::aTexto($f['descripcion'])) !== '') {
                        $parte['description'] = (string) $f['descripcion'];
                    }
                    $partes[] = $parte;
                }

                return ['mode' => 'formdata', 'formdata' => $partes];

            case 'binary':
                $info = json_decode($body, true);

                return ['mode' => 'file', 'file' => ['src' => self::aTexto(is_array($info) ? ($info['nombre_archivo'] ?? '') : '')]];

            case 'graphql':
                $g = json_decode($body, true);
                $g = is_array($g) ? $g : [];
                $variables = $g['variables'] ?? '';

                return ['mode' => 'graphql', 'graphql' => [
                    'query' => self::aTexto($g['query'] ?? ''),
                    'variables' => is_string($variables) ? $variables : self::aTexto($variables),
                ]];

            default:
                return null;
        }
    }

    // heredar → sin auth (Postman hereda cuando falta); custom → apikey en header (Postman no tiene "header personalizado").
    private static function authAV21(string $tipo, $data): ?array
    {
        $d = is_array($data) ? $data : [];
        switch ($tipo) {
            case 'heredar':
                return null;
            case 'none':
                return ['type' => 'noauth'];
            case 'bearer':
                return ['type' => 'bearer', 'bearer' => [self::paramAV21('token', $d['token'] ?? '')]];
            case 'basic':
                return ['type' => 'basic', 'basic' => [self::paramAV21('username', $d['username'] ?? ''), self::paramAV21('password', $d['password'] ?? '')]];
            case 'api-key':
                return ['type' => 'apikey', 'apikey' => [
                    self::paramAV21('key', $d['key'] ?? ''),
                    self::paramAV21('value', $d['value'] ?? ''),
                    self::paramAV21('in', ($d['in'] ?? 'header') === 'query' ? 'query' : 'header'),
                ]];
            case 'custom':
                return ['type' => 'apikey', 'apikey' => [
                    self::paramAV21('key', trim(self::aTexto($d['header'] ?? '')) ?: 'Authorization'),
                    self::paramAV21('value', $d['value'] ?? ''),
                    self::paramAV21('in', 'header'),
                ]];
            case 'oauth2':
                $params = [];
                $mapa = ['access_token' => 'accessToken', 'token_url' => 'accessTokenUrl', 'client_id' => 'clientId', 'client_secret' => 'clientSecret',
                    'scope' => 'scope', 'username' => 'username', 'password' => 'password'];
                foreach ($mapa as $nuestro => $dePostman) {
                    if (isset($d[$nuestro]) && self::aTexto($d[$nuestro]) !== '') {
                        $params[] = self::paramAV21($dePostman, $d[$nuestro]);
                    }
                }
                $grant = self::aTexto($d['grant_type'] ?? '');
                if ($grant !== '') {
                    $params[] = self::paramAV21('grant_type', $grant === 'password' ? 'password_credentials' : $grant);
                }
                $params[] = self::paramAV21('tokenType', 'Bearer');
                $params[] = self::paramAV21('addTokenTo', 'header');

                return ['type' => 'oauth2', 'oauth2' => $params];
            default:
                return null;
        }
    }

    private static function paramAV21(string $key, $valor): array
    {
        return ['key' => $key, 'value' => self::aTexto($valor), 'type' => 'string'];
    }

    // Ejemplo → response {name, originalRequest, status, code, _postman_previewlanguage, header[], cookie[], body, responseTime}
    private static function ejemploAV21(array $e, array $request): array
    {
        $snapshot = is_array($e['request_snapshot'] ?? null) && $e['request_snapshot'] ? $e['request_snapshot'] : $request;
        $contentType = self::aTexto($e['respuesta_content_type'] ?? '');
        $respuesta = [
            'name' => trim(self::aTexto($e['nombre'] ?? '')) ?: 'Ejemplo',
            'originalRequest' => self::requestCuerpoAV21($snapshot, false),
        ];
        if (isset($e['respuesta_status']) && is_numeric($e['respuesta_status'])) {
            $codigo = (int) $e['respuesta_status'];
            $respuesta['status'] = self::TEXTO_STATUS[$codigo] ?? '';
            $respuesta['code'] = $codigo;
        }
        $respuesta['_postman_previewlanguage'] = self::previewLanguage($contentType);
        $respuesta['header'] = self::headersRespuestaAV21($e['respuesta_headers'] ?? null);
        $respuesta['cookie'] = [];
        $respuesta['body'] = isset($e['respuesta_body']) && $e['respuesta_body'] !== null ? self::aTexto($e['respuesta_body']) : null;
        if (isset($e['respuesta_tiempo_ms']) && is_numeric($e['respuesta_tiempo_ms'])) {
            $respuesta['responseTime'] = (int) $e['respuesta_tiempo_ms'];
        }

        return $respuesta;
    }

    // {Nombre: valor} → [{key, value}]; un valor con saltos de línea (varios Set-Cookie) sale como varias entradas.
    private static function headersRespuestaAV21($headers): array
    {
        $salida = [];
        foreach (is_array($headers) ? $headers : [] as $k => $v) {
            if (is_array($v) && isset($v['key'])) {
                $k = $v['key'];
                $v = $v['value'] ?? '';
            }
            $key = trim(self::aTexto($k));
            if ($key === '') {
                continue;
            }
            foreach (explode("\n", self::aTexto($v)) as $linea) {
                $salida[] = ['key' => $key, 'value' => $linea];
            }
        }

        return $salida;
    }

    private static function previewLanguage(string $contentType): string
    {
        $ct = strtolower($contentType);
        if (strpos($ct, 'json') !== false) {
            return 'json';
        }
        if (strpos($ct, 'html') !== false) {
            return 'html';
        }
        if (strpos($ct, 'xml') !== false) {
            return 'xml';
        }

        return 'text';
    }

    private function contentTypeDesdePreview(string $lenguaje): ?string
    {
        switch (strtolower(trim($lenguaje))) {
            case 'json':
                return 'application/json';
            case 'html':
                return 'text/html';
            case 'xml':
                return 'application/xml';
            case 'text':
                return 'text/plain';
            default:
                return null;
        }
    }

    // ------------------------------------------------------------------
    // Ayudantes
    // ------------------------------------------------------------------

    // JSON de filas {key, value, activo...}; un objeto plano {"a": "b"} (requests viejos) se convierte en filas.
    private static function filasDesdeJson(string $body): array
    {
        $datos = json_decode($body, true);

        return self::filasDesdeLista($datos);
    }

    private static function filasDesdeLista($datos): array
    {
        if (!is_array($datos)) {
            return [];
        }
        if ($datos && array_keys($datos) !== range(0, count($datos) - 1)) {
            $filas = [];
            foreach ($datos as $k => $v) {
                $filas[] = ['key' => (string) $k, 'value' => self::aTexto($v), 'activo' => true];
            }

            return $filas;
        }

        return array_values($datos);
    }

    // basename del src de Postman ("/C:/Users/x/a.png", "C:\\x\\a.png" o lista de rutas) → "a.png"; vacío → null
    private function nombreArchivo($src): ?string
    {
        if (is_array($src)) {
            $src = reset($src);
        }
        $ruta = trim($this->texto($src));
        if ($ruta === '') {
            return null;
        }
        $nombre = basename(str_replace('\\', '/', $ruta));

        return $nombre !== '' ? $nombre : null;
    }

    private function pareceJson(string $texto): bool
    {
        $t = trim($texto);
        if ($t === '' || ($t[0] !== '{' && $t[0] !== '[')) {
            return false;
        }
        json_decode($t);

        return json_last_error() === JSON_ERROR_NONE;
    }

    // description de Postman: texto o {content, type} → texto (null si está vacía)
    private function descripcion($d): ?string
    {
        if (is_array($d)) {
            $d = $d['content'] ?? null;
        }
        $texto = trim($this->texto($d));

        return $texto === '' ? null : $texto;
    }

    private function texto($valor): string
    {
        return self::aTexto($valor);
    }

    private function json($valor, bool $bonito = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($bonito ? JSON_PRETTY_PRINT : 0);

        return (string) json_encode($valor, $flags);
    }

    public static function aTexto($valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }
        if ($valor === null || is_scalar($valor)) {
            return (string) $valor;
        }

        return (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
