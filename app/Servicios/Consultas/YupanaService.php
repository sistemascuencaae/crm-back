<?php

namespace App\Servicios\Consultas;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

// 30-oct: Yupana como calificador (PLANES/YUPANA/guia-tecnica-almacenes-espana.md), con la misma forma que
// GarancheckService (ProveedorConsulta). consultar() paga la consulta: los datos del cliente (obtenerDatosCliente) y el
// perfil (perfilCliente), y deja UNA fila en crm.proveedor_respuestas por CADA llamada (TOKEN, DATOS, PERFIL), buena o
// mala. El JSON que devuelve es el que se guarda en crm.cliente_historial_consultas.valor:
//   {proveedor, tipoIdentificacion, codTienda, datos, perfil|null, perfilError|null, perfilCodigo|null}
// Decisiones del usuario: traceId y nombreUsuario fijos del .env; el token se guarda 55 min (dura 1 h) y con un 401 se
// renueva una vez; si llegan los datos pero falla el perfil, la consulta se guarda igual (sin calificación) y el perfil
// se vuelve a pedir con Reintentar (consultarPerfil). Si Yupana no encuentra a la persona (NO_ENCONTRADO), se trata como
// "sin identidad" y el que llama cae al respaldo (Ecuador Legal / SRI).
class YupanaService implements ProveedorConsulta
{
    private const NOMBRE = 'YUPANA';

    // Qué llamada fue (columna servicio de crm.proveedor_respuestas).
    private const SERVICIO_TOKEN = 'TOKEN';
    private const SERVICIO_DATOS = 'DATOS';
    private const SERVICIO_PERFIL = 'PERFIL';

    // Ruta y eventType de cada llamada (el resto del sobre sale del .env).
    private const RUTA_DATOS = '/customer/datos-cliente-completa';
    private const EVENTO_DATOS = 'obtenerDatosCliente';
    private const RUTA_PERFIL = '/profile/perfil-cliente';
    private const EVENTO_PERFIL = 'perfilCliente';

    private const CLAVE_TOKEN = 'yupana:token';
    private const TOKEN_MINUTOS = 55;

    private const CLAVES_CONFIG = ['token_url', 'token_basic', 'user', 'pass', 'url', 'empresa', 'origen', 'canal', 'trace_id', 'cod_b2b', 'modelo_id', 'nombre_usuario'];

    // Código de tienda (codTienda) y agencia con cuyo calificador se consulta: los da crm.fn_cliente_consultas_proveedor_decidir.
    private ?string $codigoTienda;
    private ?string $agenciaCodigo;

    // El id de crm.proveedor_respuestas de la llamada principal (DATOS; o la que falló), el "Cód. N" del aviso.
    private ?int $idRespuesta = null;

    public function __construct(?string $codigoTienda = null, ?string $agenciaCodigo = null)
    {
        $this->codigoTienda = $codigoTienda !== null ? trim($codigoTienda) : null;
        $this->agenciaCodigo = $agenciaCodigo;
    }

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function nombreVisible(): string
    {
        return 'Yupana';
    }

    public function idUltimaRespuesta(): ?int
    {
        return $this->idRespuesta;
    }

    // La consulta pagada: datos + perfil. Lanza si no hay configuración, si no se consigue el token o si los DATOS fallan
    // (la fila de la llamada queda registrada antes). Un perfil que falla NO tumba la consulta: queda perfil null con el
    // motivo y el código de su fila.
    public function consultar(string $identificacion, array $registro = []): array
    {
        $this->idRespuesta = null;
        $fila = ['proveedor' => self::NOMBRE, 'identificacion' => $identificacion, 'agencia_codigo' => $this->agenciaCodigo] + $registro;
        $config = $this->configuracion($fila);
        $tipo = $this->tipoIdentificacion($identificacion);

        $datos = $this->llamar(self::SERVICIO_DATOS, self::RUTA_DATOS, self::EVENTO_DATOS, [
            'codB2B' => $config['cod_b2b'],
            'codTienda' => $this->codigoTienda,
            'tipoIdentificacion' => $tipo,
            'identificacion' => $identificacion,
        ], $fila, $config);
        $idDatos = $this->idRespuesta;

        $perfil = null;
        $perfilError = null;
        $perfilCodigo = null;
        try {
            $perfil = $this->pedirPerfil($identificacion, $tipo, $datos['idCliente'] ?? null, $fila, $config);
        } catch (RuntimeException $e) {
            $perfilError = $e->getMessage();
            $perfilCodigo = $this->idRespuesta;
        }
        $this->idRespuesta = $idDatos;

        return [
            'proveedor' => self::NOMBRE,
            'tipoIdentificacion' => $tipo,
            'codTienda' => $this->codigoTienda,
            'datos' => $datos,
            'perfil' => $perfil,
            'perfilError' => $perfilError,
            'perfilCodigo' => $perfilCodigo,
        ];
    }

    // Solo el perfil (Reintentar cuando los datos ya se guardaron sin calificación). Devuelve data[0] de perfilCliente.
    public function consultarPerfil(string $identificacion, ?string $idCliente, array $registro = []): array
    {
        $this->idRespuesta = null;
        $fila = ['proveedor' => self::NOMBRE, 'identificacion' => $identificacion, 'agencia_codigo' => $this->agenciaCodigo] + $registro;
        $config = $this->configuracion($fila);

        return $this->pedirPerfil($identificacion, $this->tipoIdentificacion($identificacion), $idCliente, $fila, $config);
    }

    // Datos mínimos para crear o corregir al cliente. Yupana trae nombres y apellidos por separado; para una empresa (RUC)
    // no trae razón social, así que lo que venga como nombre va completo en apellidos y '.' en nombres (estándar del ERP).
    // Las direcciones vienen con ids del catálogo de Yupana, no con nombres: la geo cae a la provincia por defecto.
    public function extraerDatosCliente(array $json, string $tipoSujeto): array
    {
        $d = is_array($json['datos'] ?? null) ? $json['datos'] : [];
        $estado = strtoupper(trim((string) ($d['estado'] ?? '')));
        if ($estado !== 'EXISTE') {
            throw new RuntimeException('El proveedor no devolvió los datos de identidad (Yupana: ' . ($estado !== '' ? $estado : 'sin datos') . '); no se pudo registrar al cliente.');
        }

        $nombres = $this->textoMayusculas($d['primerNombre'] ?? null, $d['segundoNombre'] ?? null);
        $apellidos = $this->textoMayusculas($d['primerApellido'] ?? null, $d['segundoApellido'] ?? null);

        if ($tipoSujeto === 'J') {
            $razonSocial = trim($apellidos . ' ' . $nombres);
            if ($razonSocial === '') {
                throw new RuntimeException('El proveedor no devolvió los datos de identidad (Yupana: sin razón social); no se pudo registrar al cliente.');
            }
            $apellidos = $razonSocial;
            $nombres = '.';
        } elseif ($nombres === '' && $apellidos === '') {
            throw new RuntimeException('El proveedor no devolvió los datos de identidad (Yupana: sin nombres); no se pudo registrar al cliente.');
        }

        $direccion = $this->direccionPrincipal($d['direcciones'] ?? null);

        return [
            'tipo_sujeto' => $tipoSujeto === 'J' ? 'J' : 'N',
            'nombres' => $nombres !== '' ? $nombres : null,
            'apellidos' => $apellidos !== '' ? $apellidos : null,
            'nombre_comercial' => trim($apellidos . ' ' . $nombres) !== '' ? trim($apellidos . ' ' . $nombres) : null,
            'email' => $this->email($d['correo'] ?? null),
            'telefono' => $this->telefono($d['celular'] ?? null, $d['telefonos'] ?? null),
            'direccion' => $direccion['calle'],
            'provincia' => null,
            'canton' => null,
            'parroquia' => null,
            // Los ids de Yupana de la dirección principal (provinciaId, cantonId, parroquiaId), por si se casan después.
            'geo_proveedor' => $direccion['ids'],
        ];
    }

    // Lo de extraerDatosCliente más lo personal que trae Yupana. No trae estado civil, nivel de estudios, nacionalidad
    // por nombre ni cónyuge: van null.
    public function extraerDatosPrecarga(array $json, string $tipoSujeto): array
    {
        $datos = $this->extraerDatosCliente($json, $tipoSujeto);
        $d = is_array($json['datos'] ?? null) ? $json['datos'] : [];

        if ($tipoSujeto === 'J') {
            return $datos + [
                'fecha_nacimiento' => null,
                'sexo' => null,
                'estado_civil' => null,
                'nivel_estudios' => null,
                'nacionalidad' => null,
                'conyuge' => null,
                'id_cliente_proveedor' => $d['idCliente'] ?? null,
            ];
        }

        $genero = strtoupper(trim((string) ($d['generoId'] ?? '')));

        return $datos + [
            'fecha_nacimiento' => $this->fechaIso($d['fechaNacimiento'] ?? null),
            'sexo' => $genero === 'M' ? 'MASCULINO' : ($genero === 'F' ? 'FEMENINO' : null),
            'estado_civil' => null,
            'nivel_estudios' => null,
            'nacionalidad' => null,
            'conyuge' => null,
            'id_cliente_proveedor' => $d['idCliente'] ?? null,
        ];
    }

    // La calificación para la pantalla: el perfil (ORO, PLATA...), el resultado (APROBAR AAA) y el score. null si la
    // consulta quedó sin perfil (falló perfilCliente): el front muestra el aviso con Reintentar.
    public function evaluacionParaPantalla(array $json): ?array
    {
        $perfil = $json['perfil'] ?? null;
        if (!is_array($perfil)) {
            return null;
        }

        $info = is_array($perfil['informacionPerfil'] ?? null) ? $perfil['informacionPerfil'] : [];

        return [
            'proveedor' => self::NOMBRE,
            'perfil' => $info['perfil'] ?? null,
            'descripcionPerfil' => $info['descripcionPerfil'] ?? null,
            'segmentoEquifax' => $info['segmentoEquifax'] ?? null,
            'formalidad' => $info['formalidad'] ?? null,
            'clasificacion' => $info['clasificacion'] ?? null,
            'tipoModelo' => $info['tipoModelo'] ?? null,
            'origen' => $info['origen'] ?? null,
            'fechaUpd' => $info['fechaUpd'] ?? null,
            'numeroPagos' => $info['numeroPagos'] ?? null,
            'moraPromedio' => $info['moraPromedio'] ?? null,
            'anios' => $info['anios'] ?? null,
            'resultadoCalificadora' => $perfil['resultadoCalificadora'] ?? null,
            'scoreCalificadora' => $perfil['scoreCalificadora'] ?? null,
            'idCliente' => $perfil['idCliente'] ?? null,
            'flujoFinal' => $perfil['flujoFinal'] ?? null,
        ];
    }

    // =====================================================================================
    // PRIVADOS
    // =====================================================================================

    private function pedirPerfil(string $identificacion, string $tipo, $idCliente, array $fila, array $config): array
    {
        $data = [
            'codB2B' => $config['cod_b2b'],
            'codTienda' => $this->codigoTienda,
            'tipoIdentificacion' => $tipo,
            'identificacion' => $identificacion,
            'nombreUsuario' => $config['nombre_usuario'],
            'modeloId' => (int) $config['modelo_id'],
        ];
        // Opcional en el contrato: sin él la perfilación sigue sin ese identificador.
        if ($idCliente !== null && trim((string) $idCliente) !== '') {
            $data['idCliente'] = (string) $idCliente;
        }

        return $this->llamar(self::SERVICIO_PERFIL, self::RUTA_PERFIL, self::EVENTO_PERFIL, $data, $fila, $config);
    }

    // Una llamada al servicio con el sobre del contrato: token de la caché (o nuevo), POST, y con un 401 un token nuevo y
    // un solo reintento. Registra su fila y devuelve data[0]. Lanza RuntimeException con el mensaje para el usuario.
    private function llamar(string $servicio, string $ruta, string $evento, array $data, array $fila, array $config): array
    {
        $filaServicio = ['servicio' => $servicio] + $fila;
        $inicio = microtime(true);
        $cuerpo = [
            'empresa' => $config['empresa'],
            'origen' => $config['origen'],
            'canal' => $config['canal'],
            'eventType' => $evento,
            'traceId' => $config['trace_id'],
            'data' => [$data],
        ];

        $token = $this->token($fila, $config);

        try {
            $response = $this->post($config, $token, $ruta, $cuerpo);
            if ($response->status() === 401) {
                Cache::forget(self::CLAVE_TOKEN);
                $token = $this->token($fila, $config, true);
                $response = $this->post($config, $token, $ruta, $cuerpo);
            }
        } catch (Throwable $e) {
            $this->registrar($filaServicio, ProveedorRespuestasService::SIN_RESPUESTA, $inicio, $e->getMessage());
            throw new RuntimeException('El proveedor no respondió: ' . $e->getMessage());
        }

        $json = $response->json();

        if (!$response->successful()) {
            $detalle = is_array($json) ? trim((string) ($json['detail'] ?? $json['title'] ?? '')) : '';
            $this->registrar($filaServicio, ProveedorRespuestasService::ERROR_HTTP, $inicio,
                'HTTP ' . $response->status() . ($detalle !== '' ? ': ' . $detalle : ''), $response->status(), $response->body());
            throw new RuntimeException('El proveedor respondió con error HTTP ' . $response->status());
        }

        if (!is_array($json) || empty($json)) {
            $this->registrar($filaServicio, ProveedorRespuestasService::RESPUESTA_INVALIDA, $inicio, 'Respuesta vacía o que no es JSON.', $response->status(), $response->body());
            throw new RuntimeException('El proveedor devolvió una respuesta vacía o inválida.');
        }

        if (empty($json['success']) || !is_array($json['data'][0] ?? null)) {
            $mensaje = trim((string) ($json['detail'] ?? $json['title'] ?? ''));
            $mensaje = $mensaje !== '' ? $mensaje : 'respuesta sin datos';
            $this->registrar($filaServicio, ProveedorRespuestasService::ERROR_PROVEEDOR, $inicio, $mensaje, $response->status(), $json);
            throw new RuntimeException('Proveedor: ' . $mensaje);
        }

        $this->registrar($filaServicio, ProveedorRespuestasService::OK, $inicio, null, $response->status());

        return $json['data'][0];
    }

    private function post(array $config, string $token, string $ruta, array $cuerpo)
    {
        return Http::withToken($token)
            ->withHeaders(['X-Trace-Id' => $config['trace_id']])
            ->acceptJson()
            ->timeout($config['timeout'])
            ->post(rtrim($config['url'], '/') . $ruta, $cuerpo);
    }

    // El token de IAM: de la caché (TOKEN_MINUTOS) o uno nuevo. Cada pedido nuevo deja su fila TOKEN; si falla, lanza.
    private function token(array $fila, array $config, bool $forzar = false): string
    {
        if (!$forzar) {
            $guardado = Cache::get(self::CLAVE_TOKEN);
            if (is_string($guardado) && $guardado !== '') {
                return $guardado;
            }
        }

        $filaToken = ['servicio' => self::SERVICIO_TOKEN] + $fila;
        $inicio = microtime(true);
        $basic = trim((string) $config['token_basic']);
        $basic = preg_match('/^basic /i', $basic) ? $basic : 'Basic ' . $basic;

        try {
            $response = Http::withHeaders(['Authorization' => $basic])
                ->acceptJson()
                ->timeout($config['timeout'])
                ->post($config['token_url'], ['username' => $config['user'], 'password' => $config['pass']]);
        } catch (Throwable $e) {
            $this->registrar($filaToken, ProveedorRespuestasService::SIN_RESPUESTA, $inicio, 'Token: ' . $e->getMessage());
            throw new RuntimeException('El proveedor no respondió: ' . $e->getMessage());
        }

        $json = $response->json();

        if (!$response->successful()) {
            $this->registrar($filaToken, ProveedorRespuestasService::ERROR_HTTP, $inicio, 'Token: HTTP ' . $response->status(), $response->status(), $response->body());
            throw new RuntimeException('El proveedor respondió con error HTTP ' . $response->status());
        }

        $token = is_array($json)
            ? (string) ($json['access_token'] ?? $json['token'] ?? ($json['data']['access_token'] ?? $json['data']['token'] ?? ''))
            : '';
        if (trim($token) === '') {
            $this->registrar($filaToken, ProveedorRespuestasService::RESPUESTA_INVALIDA, $inicio, 'Token: respuesta sin access_token.', $response->status(), $response->body());
            throw new RuntimeException('El proveedor devolvió una respuesta vacía o inválida.');
        }

        $this->registrar($filaToken, ProveedorRespuestasService::OK, $inicio, null, $response->status());
        Cache::put(self::CLAVE_TOKEN, $token, now()->addMinutes(self::TOKEN_MINUTOS));

        return $token;
    }

    // services.yupana completo y el código de tienda de la agencia; si falta algo, fila SIN_CONFIGURACION y lanza.
    private function configuracion(array $fila): array
    {
        $config = (array) config('services.yupana');
        $faltan = [];
        foreach (self::CLAVES_CONFIG as $clave) {
            if (trim((string) ($config[$clave] ?? '')) === '') {
                $faltan[] = $clave;
            }
        }

        if ($faltan) {
            $this->registrar(['servicio' => self::SERVICIO_DATOS] + $fila, ProveedorRespuestasService::SIN_CONFIGURACION, microtime(true),
                'Proveedor no configurado (services.yupana: ' . implode(', ', $faltan) . ').');
            throw new RuntimeException('Proveedor no configurado, comuníquese con el administrador.');
        }

        if ($this->codigoTienda === null || $this->codigoTienda === '') {
            $this->registrar(['servicio' => self::SERVICIO_DATOS] + $fila, ProveedorRespuestasService::SIN_CONFIGURACION, microtime(true),
                'La agencia no tiene código de tienda de Yupana.');
            throw new RuntimeException('Proveedor no configurado, comuníquese con el administrador.');
        }

        $config['timeout'] = (int) ($config['timeout'] ?? 60) ?: 60;

        return $config;
    }

    // La fila de crm.proveedor_respuestas de una llamada. Nunca lanza.
    private function registrar(array $fila, string $resultado, float $inicio, ?string $mensaje, ?int $httpStatus = null, $respuesta = null): void
    {
        $id = ProveedorRespuestasService::registrar($fila + [
            'resultado' => $resultado,
            'mensaje' => $mensaje,
            'http_status' => $httpStatus,
            'duracion_ms' => ProveedorRespuestasService::milisegundos($inicio),
            'respuesta' => $respuesta,
        ]);
        $this->idRespuesta = $id ?? $this->idRespuesta;
    }

    // CED (10 dígitos), RUC (13); el contrato también acepta PAS, que aquí no se usa.
    private function tipoIdentificacion(string $identificacion): string
    {
        return preg_match('/^\d{13}$/', $identificacion) ? 'RUC' : 'CED';
    }

    // La dirección principal: la primera con calle; la calle en mayúsculas o 'SN', y los ids del catálogo de Yupana.
    private function direccionPrincipal($direcciones): array
    {
        $elegida = null;
        if (is_array($direcciones)) {
            foreach ($direcciones as $direccion) {
                if (is_array($direccion) && trim((string) ($direccion['callePrincipal'] ?? '')) !== '') {
                    $elegida = $direccion;
                    break;
                }
            }
            if ($elegida === null && isset($direcciones[0]) && is_array($direcciones[0])) {
                $elegida = $direcciones[0];
            }
        }

        $calle = trim(preg_replace('/\s+/', ' ', (string) ($elegida['callePrincipal'] ?? '')));
        $numero = trim((string) ($elegida['numero'] ?? ''));
        if ($calle !== '' && $numero !== '') {
            $calle .= ' ' . $numero;
        }

        return [
            'calle' => $calle !== '' ? mb_strtoupper($calle, 'UTF-8') : 'SN',
            'ids' => $elegida === null ? null : [
                'provinciaId' => $elegida['provinciaId'] ?? null,
                'cantonId' => $elegida['cantonId'] ?? null,
                'parroquiaId' => $elegida['parroquiaId'] ?? null,
                'ubicacionId' => $elegida['ubicacionId'] ?? null,
                'referencia' => $elegida['referencia'] ?? null,
            ],
        ];
    }

    // Celular (09 + 8 dígitos) o, si no, el primer teléfono con dígitos; 'SN' si no hay ninguno. Solo dígitos.
    private function telefono($celular, $telefonos): string
    {
        $candidatos = [];
        $soloDigitos = preg_replace('/\D+/', '', (string) $celular);
        if ($soloDigitos !== '') {
            $candidatos[] = $soloDigitos;
        }
        if (is_array($telefonos)) {
            foreach ($telefonos as $telefono) {
                $numero = preg_replace('/\D+/', '', (string) (is_array($telefono) ? ($telefono['numero'] ?? '') : $telefono));
                if ($numero !== '') {
                    $candidatos[] = $numero;
                }
            }
        }

        foreach ($candidatos as $numero) {
            if (preg_match('/^09\d{8}$/', $numero)) {
                return $numero;
            }
        }

        return $candidatos[0] ?? 'SN';
    }

    private function email($correo): ?string
    {
        $correo = trim((string) $correo);

        return $correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) ? mb_strtolower($correo, 'UTF-8') : null;
    }

    private function textoMayusculas(...$partes): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', implode(' ', array_map(fn ($p) => trim((string) $p), $partes))));

        return $texto !== '' ? mb_strtoupper($texto, 'UTF-8') : '';
    }

    // 'Y-m-d' o null (el contrato manda yyyy-MM-dd).
    private function fechaIso($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1900) {
            return null;
        }

        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
}
