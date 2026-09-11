<?php

namespace App\Http\Controllers\pinpad;

// Controller base de Laravel (provee helpers como middleware, validate, etc).
use App\Http\Controllers\Controller;
// Helper interno del proyecto que estandariza el formato de las respuestas JSON.
use App\Http\Resources\RespuestaApi;
// Servicios del modulo Pin Pad (los que comente antes).
use App\Servicios\Pinpad\CifradoTramas;
use App\Servicios\Pinpad\Conexion;
use App\Servicios\Pinpad\Trama;
use App\Servicios\Pinpad\TramaCache;
// Request: objeto que envuelve la peticion HTTP (params, body, headers).
use Illuminate\Http\Request;
// Log: facade para escribir al storage/logs/laravel.log.
use Illuminate\Support\Facades\Log;

/**
 * PinpadController — controlador principal del modulo Pin Pad.
 *
 * Expone TODAS las operaciones del manual oficial v1.4:
 *   - Utilidades: probe, hash, raw
 *   - PP: cobrar, diferido, anular, reverso, maxidolar
 *   - CT: cierreTurno
 *   - LT: lectura
 *   - PC: cierreLote
 *   - CP: cambioParametros
 *   - RA: reimpresion
 *
 * Patron comun de cada endpoint:
 *   1) try/catch para atrapar cualquier error
 *   2) $req->validate() para validar inputs
 *   3) Construir payload incluyendo MID/TID del .env
 *   4) Trama::buildXxx() para armar la trama
 *   5) enviarYParsear() para enviar y procesar respuesta
 *   6) response()->json(RespuestaApi::...) formato consistente
 */
class PinpadController extends Controller
{
    public function __construct()
    {
        // El middleware auth:api validaria el token JWT antes de cada accion.
        // Se deja comentado mientras esta en desarrollo, pero en produccion
        // se debe activar para que solo usuarios autenticados puedan cobrar.
        // $this->middleware('auth:api');
    }

    // ============================================================
    // UTILIDADES — endpoints de diagnostico y debugging
    // ============================================================

    /**
     * probe() - GET /pinpad/probe
     *
     * Hace un TCP connect al Pin Pad y cierra inmediatamente. Sirve para
     * confirmar que la red lo alcanza, sin enviar transacciones reales.
     *
     * Util como primer "ping" al integrar una caja nueva o para diagnostico
     * cuando algo no funciona.
     */
    public function probe()
    {
        try {
            // Lee config/pinpad.php (que a su vez lee del .env).
            $ip = config('pinpad.ip');
            $port = (int) config('pinpad.port');

            // Conexion::probe abre socket con timeout de 3s y cierra inmediato.
            // Devuelve true/false sin lanzar excepcion (para no asustar).
            $ok = Conexion::probe($ip, $port, 3000);

            $data = [
                'pinpad' => "$ip:$port",
                'reachable' => $ok,
                'host_medianet' => config('pinpad.host_medianet') . ' (uso interno del Pin Pad)',
                // Devueltos al frontend para el encabezado del voucher.
                'mid' => config('pinpad.mid'),
                'tid' => config('pinpad.tid'),
                'comercio' => config('pinpad.comercio'),
            ];
            $estado = $ok ? 'success' : 'error';
            $msg = $ok ? "Pin Pad accesible en $ip:$port" : "Pin Pad NO accesible en $ip:$port";
            return response()->json(RespuestaApi::returnResultado($estado, $msg, $data));
        } catch (\Throwable $th) {
            // Throwable atrapa Exception y Error. Devolvemos info al frontend
            // sin tirar el HTTP 500 (que seria peor UX).
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    /**
     * hash() - GET /pinpad/hash
     *
     * Genera un hash 3DES y lo valida. Sirve como test del algoritmo:
     * si esto funciona, sabemos que phpseclib esta cargado, las llaves
     * son correctas y el cifrado/descifrado son consistentes.
     */
    public function hash()
    {
        try {
            $h = CifradoTramas::getHash();
            return response()->json(RespuestaApi::returnResultado('success', 'Hash generado', [
                'hash' => $h, // los 32 chars hex
                'len' => strlen($h), // siempre debe ser 32
                'valido' => CifradoTramas::validateHash($h), // round-trip check
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    /**
     * raw() - POST /pinpad/raw
     *
     * Envia una trama YA construida sin validar contenido. Util para:
     *   - Debug: probar tramas armadas a mano o desde el JAR oficial
     *   - El componente /pinpad-libreria-jar usa este endpoint para
     *     enviar lo que el JavaFX genero
     *
     * Body esperado: { "trama": "00d4PP01..." }
     */
    public function raw(Request $req)
    {
        try {
            // Validacion: 'trama' debe existir, ser string y tener al menos 5 chars.
            $req->validate(['trama' => 'required|string|min:5']);

            // Si la trama no trae prefijo de longitud "00d4...", se agrega automaticamente.
            $trama = Trama::ensurePrefix($req->input('trama'));

            // enviarYParsear: TCP send + parse respuesta + formato JSON.
            return response()->json($this->enviarYParsear($trama));
        } catch (\Throwable $th) {
            Log::error('Pinpad/raw: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // PP - PROCESO DE PAGO
    // ============================================================

    /**
     * cobrar() - POST /pinpad/cobrar
     * PP Corriente: cobro estandar con tarjeta.
     *
     * Body esperado (en dolares):
     *   total, base15, base0, iva  → requeridos
     *   servicio, propina          → opcionales (solo para restaurantes)
     *
     * Las reglas de validate() aseguran que todo sea numerico positivo.
     * 'min:0.01' en total = no permite cobro de $0.
     */
    public function cobrar(Request $req)
    {
        return $this->enviarPp('corriente', $req, [
            'total' => 'required|numeric|min:0.01',
            'base15' => 'required|numeric|min:0',
            'base0' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'servicio' => 'nullable|numeric|min:0',
            'propina' => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * diferido() - POST /pinpad/diferido
     * PP Diferido: cobro a cuotas. Requiere modalidad (subtipo) y plazo.
     *
     * El subtipo determina si tiene interes y de que tipo (Normal, Gracia,
     * Mes a Mes, Especial). Mapea al MOD code en la trama.
     */
    public function diferido(Request $req)
    {
        $modalidad = $req->input('modalidad', 'diferido_normal_con');
        if (!str_starts_with($modalidad, 'diferido_')) {
            return response()->json(RespuestaApi::returnResultado('error', 'modalidad debe ser diferido_*', null));
        }
        return $this->enviarPp($modalidad, $req, [
            'modalidad' => 'required|string|in:' . implode(',', array_keys(Trama::PP_MODS)),
            'total' => 'required|numeric|min:0.01',
            'base15' => 'required|numeric|min:0',
            'base0' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'plazo' => 'required|integer|min:1|max:99',
            'gracia_meses' => 'nullable|integer|min:0|max:99',
            'servicio' => 'nullable|numeric|min:0',
            'propina' => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * anular() - POST /pinpad/anular
     * PP Anulacion: cancela una transaccion del MISMO DIA.
     *
     * Para anular se requiere el numero de referencia (secuencial) de la
     * transaccion original y, segun el manual p.6, el numero de autorizacion
     * que el banco devolvio en esa compra (ambos vienen en data.detalle del
     * cobro y en el voucher impreso). Los montos van en cero automaticamente
     * (el switch ya los conoce).
     */
    public function anular(Request $req)
    {
        return $this->enviarPp('anulacion', $req, [
            'referencia' => 'required|string',
            'autorizacion' => 'nullable|string|max:6',
        ]);
    }

    /**
     * reverso() - POST /pinpad/reverso
     *
     * PP Reverso: deshace la ultima transaccion. Logica especial:
     *
     *   1) PRIMERO: busca en cache una trama de reverso pre-armada
     *      (TramaCache la guarda tras cada PP/RA exitosa).
     *   2) SI HAY: la envia tal cual. La trama tiene el mismo hash, fecha,
     *      hora y montos que la transaccion original — el switch puede
     *      matchearla y reversarla.
     *   3) SI NO HAY: arma un reverso "vacio" desde cero (probablemente
     *      el switch lo rechace por no encontrar transaccion original,
     *      pero al menos lo intenta).
     *   4) Tras un reverso EXITOSO, limpia la cache (no hay 2 reversos posibles).
     */
    public function reverso(Request $req)
    {
        try {
            // Lee el TID del .env (clave del cache).
            $tid = config('pinpad.tid');
            // Recupera la trama cacheada (o null).
            $cached = TramaCache::getReverso($tid);

            if ($cached) {
                // Caso bueno: hay reverso cacheado, lo enviamos tal cual.
                $resp = $this->enviarYParsear($cached, ['operacion' => 'reverso', 'fuente' => 'cache']);

                // Si el switch lo aprobo, limpiamos cache para evitar dobles reversos.
                if (in_array($resp['data']['cod_resp'] ?? null, ['00', '08'])) {
                    TramaCache::clearReverso($tid);
                }
                return response()->json($resp);
            }

            // Caso malo: no hay cache. Intentamos armar uno desde cero.
            // El frontend ya advirtio al usuario que no se podia (mensaje
            // "No existe transaccion para reversar"), pero igual lo tratamos.
            return $this->enviarPp('reverso', $req, []);
        } catch (\Throwable $th) {
            \Log::error('Pinpad/reverso: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    /**
     * reversoDisponible() - GET /pinpad/reverso-disponible
     *
     * Endpoint liviano (no toca el Pin Pad) que dice si hay una trama de
     * reverso cacheada en este momento. El frontend lo llama al cambiar
     * a modalidad "reverso" para mostrar mensaje informativo.
     */
    public function reversoDisponible()
    {
        $tid = config('pinpad.tid');
        return response()->json(RespuestaApi::returnResultado('success', 'Estado del cache', [
            // hasReverso() solo chequea si EXISTE la entrada (no la trae).
            'disponible' => TramaCache::hasReverso($tid),
            'tid' => $tid,
        ]));
    }

    /**
     * maxidolar() - POST /pinpad/maxidolar
     *
     * PP Maxidolar: tarjeta especial Maxidolar de Banco Bolivariano.
     * Tiene 2 modalidades:
     *   - maxidolar_consulta: solo verifica saldo, no cobra
     *   - maxidolar_pago: cobra el total especificado
     */
    public function maxidolar(Request $req)
    {
        // input('campo', $default): si no esta en body, usa default.
        $modalidad = $req->input('modalidad', 'maxidolar_consulta');
        return $this->enviarPp($modalidad, $req, [
            'modalidad' => 'required|string|in:maxidolar_consulta,maxidolar_pago',
            'total' => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * enviarPp() - helper privado compartido por TODOS los endpoints PP.
     * Evita duplicar el patron try/validate/build/send en cada metodo.
     *
     * @param string $modalidad  ej "corriente", "diferido_normal_con", "anulacion"
     * @param Request $req       request HTTP entrante
     * @param array  $rules      reglas Laravel validate() especificas de la modalidad
     */
    private function enviarPp(string $modalidad, Request $req, array $rules)
    {
        try {
            // Si hay reglas, las aplicamos. Si no (ej: reverso no requiere nada),
            // tomamos el body completo sin filtrar.
            $data = $rules ? $req->validate($rules) : $req->all();

            // Mergeamos los datos del frontend con los IDs del comercio (.env).
            // El cajero NUNCA envia MID/TID por seguridad — vienen del backend.
            $payload = array_merge($data, [
                'mid' => config('pinpad.mid'),
                'tid' => config('pinpad.tid'),
                'cid_terminal' => config('pinpad.cid_terminal'),
            ]);

            // Trama::buildPp construye los 212 chars del cuerpo + prefijo + hash.
            $trama = Trama::buildPp($modalidad, $payload);

            // Pasamos 'operacion' al helper para incluirla en la respuesta.
            // El frontend usa esto para saber que tipo de transaccion fue.
            $extra = ['operacion' => $modalidad];

            // Eco de plazo/gracia para diferidos: la respuesta del pinpad NO
            // trae campo de cuotas (manual p.8-10), asi que devolvemos lo
            // enviado para que el frontend pueda mostrarlo al cajero.
            if (isset($data['plazo'])) $extra['plazo'] = (int) $data['plazo'];
            if (isset($data['gracia_meses'])) $extra['gracia_meses'] = (int) $data['gracia_meses'];

            return response()->json($this->enviarYParsear($trama, $extra));
        } catch (\Throwable $th) {
            Log::error("Pinpad/$modalidad: " . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // CT - CIERRE DE TURNO
    // ============================================================

    /**
     * cierreTurno() - POST /pinpad/cierre-turno
     *
     * CT: cierra el turno del cajero. No requiere parametros — el Pin Pad
     * sabe a que turno aplica por su estado interno.
     *
     * Trama mas simple del manual: "CT" + HASH (34 chars de cuerpo).
     */
    public function cierreTurno()
    {
        try {
            $trama = Trama::buildCierreTurno();
            return response()->json($this->enviarYParsear($trama, ['operacion' => 'cierre_turno']));
        } catch (\Throwable $th) {
            Log::error('Pinpad/cierre-turno: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // LT - LECTURA DE TARJETA
    // ============================================================

    /**
     * lectura() - POST /pinpad/lectura
     *
     * LT: lee la tarjeta SIN cobrar. Sirve para validar que el lector
     * funciona, leer el BIN para descuentos por marca, etc.
     *
     * El monto es opcional (manual lo marca como requerido pero el bytecode
     * permite enviarlo en blanco). Si no se envia, va 0.
     */
    public function lectura(Request $req)
    {
        try {
            $data = $req->validate([
                'monto' => 'nullable|numeric|min:0',
            ]);
            // Pasamos null al builder si no hay monto, para que la trama
            // tenga "000000000000" en lugar de un valor real.
            $trama = Trama::buildLecturaTarjeta(
                isset($data['monto']) ? (float)$data['monto'] : null
            );
            return response()->json($this->enviarYParsear($trama, ['operacion' => 'lectura']));
        } catch (\Throwable $th) {
            Log::error('Pinpad/lectura: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // CP - CAMBIO DE PARAMETROS
    // ============================================================

    /**
     * cambioParametros() - POST /pinpad/cambio-parametros
     *
     * CP: reconfigura la red del Pin Pad. PELIGROSO — si los datos estan
     * mal, el equipo queda sin comunicacion.
     *
     * Validate aplica regla 'ip' que verifica formato IPv4 valido.
     * Hosts son opcionales (nullable).
     */
    public function cambioParametros(Request $req)
    {
        try {
            $data = $req->validate([
                'ip' => 'required|ip',
                'mask' => 'required|string',
                'gateway' => 'required|ip',
                'listening_port' => 'required|integer',
                'ip_host1' => 'nullable|ip',
                'port_host1' => 'nullable|integer',
                'ip_alt_host1' => 'nullable|ip',
                'port_alt_host1' => 'nullable|integer',
                'ip_host2' => 'nullable|ip',
                'port_host2' => 'nullable|integer',
                'ip_alt_host2' => 'nullable|ip',
                'port_alt_host2' => 'nullable|integer',
            ]);
            $trama = Trama::buildCambioParametros($data);
            return response()->json($this->enviarYParsear($trama, ['operacion' => 'cambio_parametros']));
        } catch (\Throwable $th) {
            Log::error('Pinpad/cambio-parametros: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // PC - PROCESO DE CONTROL (cierre de lote)
    // ============================================================

    /**
     * cierreLote() - POST /pinpad/cierre-lote
     *
     * PC: cierra el lote de transacciones del dia y las envia al switch
     * para conciliacion. SIN cierre, las ventas no llegan a la cuenta del
     * comercio. Operacion tipica de fin de dia.
     */
    public function cierreLote(Request $req)
    {
        try {
            $data = $req->validate([
                'batch' => 'nullable|integer',
                'reference' => 'nullable|integer',
            ]);
            $payload = array_merge($data, [
                'mid' => config('pinpad.mid'),
                'tid' => config('pinpad.tid'),
                'cid_terminal' => config('pinpad.cid_terminal'),
            ]);
            $trama = Trama::buildProcesoControl($payload);
            return response()->json($this->enviarYParsear($trama, ['operacion' => 'cierre_lote']));
        } catch (\Throwable $th) {
            Log::error('Pinpad/cierre-lote: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // RA - REIMPRESION / RE-AUTORIZACION
    // ============================================================

    /**
     * POST /pinpad/reimpresion - RA (Avance en Efectivo / Cash Advance)
     * Per manual seccion 4.1.6:
     *   - serial: requerido, min 10 chars max 15 alfanumerico
     *   - total: requerido y > 0 para corriente/diferido
     *   - plazo + gracia + diferido_tipo: solo si modalidad="diferido"
     *   - referencia: requerido para anulacion
     */
    public function reimpresion(Request $req)
    {
        try {
            $data = $req->validate([
                'modalidad' => 'required|string|in:corriente,diferido,anulacion,reverso',
                'serial' => 'required|string|min:10|max:15|regex:/^[A-Za-z0-9]+$/',
                'base' => 'nullable|numeric|min:0',
                'total' => 'nullable|numeric|min:0',
                'referencia' => 'nullable|string|max:6',
                'plazo' => 'nullable|integer|min:0|max:99',
                'gracia' => 'nullable|integer|min:0|max:99',
                'diferido_tipo' => 'nullable|string|in:normal_con,gracia_con,mes_a_mes_con,normal_sin,gracia_sin,mes_a_mes_sin,especial_sin',
                'adquirente_code' => 'nullable|string|max:6',
            ]);

            // Validacion adicional segun modalidad (manual 4.1.6)
            if (in_array($data['modalidad'], ['corriente', 'diferido']) && (!isset($data['total']) || $data['total'] <= 0)) {
                throw new \InvalidArgumentException('Total requerido > 0 para corriente/diferido');
            }
            if ($data['modalidad'] === 'diferido' && empty($data['diferido_tipo'])) {
                throw new \InvalidArgumentException('diferido_tipo requerido para modalidad diferido');
            }
            if ($data['modalidad'] === 'anulacion' && empty($data['referencia'])) {
                throw new \InvalidArgumentException('referencia requerida para anulacion');
            }

            $trama = Trama::buildReimpresion($data['modalidad'], $data);
            return response()->json($this->enviarYParsear($trama, ['operacion' => 'reimpresion']));
        } catch (\Throwable $th) {
            Log::error('Pinpad/reimpresion: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // HELPER PRIVADO: envio TCP + parseo + formato RespuestaApi
    // ============================================================

    /**
     * enviarYParsear() - el corazon del controlador.
     *
     * Recibe una trama ya construida, la envia al Pin Pad por TCP, parsea
     * la respuesta, decide si fue exitosa, gestiona el cache de reverso y
     * formatea todo para devolverlo como JSON al frontend.
     *
     * @param string $trama  trama lista (con prefijo + cuerpo + hash)
     * @param array $extra   datos adicionales para incluir en la respuesta
     *                       (ej: ['operacion' => 'corriente'])
     */
    private function enviarYParsear(string $trama, array $extra = []): array
    {
        // Lee config: hacia donde mandar y por cuanto tiempo esperar respuesta.
        $ip = config('pinpad.ip');
        $port = (int) config('pinpad.port');
        $timeout = (int) config('pinpad.timeout_ms');

        // Log antes de enviar (util para auditoria y debug).
        // Loguea: IP:puerto, longitud, contenido completo de la trama.
        Log::info("Pinpad -> $ip:$port (" . strlen($trama) . " bytes): $trama");

        // ===== 1) ENVIO TCP =====
        // sendRecv hace todo el ida y vuelta: open, write, read loop, close.
        // Si falla la conexion, lanza RuntimeException que el caller captura.
        $resp = Conexion::sendRecv($ip, $port, $trama, $timeout);

        // ===== 2) PARSEO =====
        // parseResponse devuelve un array con: tipo, codigo, mensaje, hash, etc.
        $parsed = Trama::parseResponse($resp);

        // Log despues de recibir (para correlacionar con el envio).
        Log::info('Pinpad <- ' . json_encode($parsed));

        // ===== 3) ARMAR PAYLOAD para el frontend =====
        // array_merge: combina los datos extra (ej: operacion) con todo lo
        // que parseamos. El frontend recibe TODOS los campos para mostrar
        // diagnostico al cajero si algo sale mal.
        $data = array_merge($extra, [
            'trama_enviada' => $trama, // lo que mandamos
            'trama_recibida' => $parsed['raw'], // lo que vino crudo
            'longitud' => $parsed['len'], // longitud declarada
            'tipo' => $parsed['tipo'], // tipo de mensaje (2 chars: PP, LT, CT...)
            'cod_pinpad' => $parsed['cod_pinpad'], // solo PP: codigo del pinpad (00=OK, TO, ER...)
            'cod_red' => $parsed['cod_red'], // solo PP: red adquirente (02=Medianet)
            'cuerpo' => $parsed['body'], // cuerpo sin hash
            'mensaje_pinpad' => $parsed['mensaje'], // texto humano (ej: "AUTORIZACION OK")
            'hash_recibido' => $parsed['hash_recibido'], // 32 chars hex del hash
            'cod_resp' => $parsed['cod_resp'], // codigo del autorizador/banco
            'cod_resp_desc' => $parsed['cod_resp_desc'], // descripcion humana
            'detalle' => $parsed['detalle'], // solo PP: 31 campos posicionales (secuencial, lote, autorizacion, tarjeta, EMV...)
        ]);

        // ===== 4) DECIDIR ESTADO Y MENSAJE =====
        // Codigo "00" = Aprobada, "08" = Aprobada VIP. Cualquier otro es error.
        $aprobada = in_array($parsed['cod_resp'], ['00', '08']);
        // 'success' o 'error' es lo que usa RespuestaApi para el color/icono
        // (verde vs rojo) que muestra el frontend.
        $estado = $aprobada ? 'success' : 'error';

        if ($aprobada) {
            $msg = 'Transaccion aprobada';
        } else {
            // Construir el mensaje de error con la mejor info disponible.
            // Prioriza el texto humano del Pin Pad si lo detectamos.
            $msg = $parsed['mensaje']
                ? 'Pinpad: ' . $parsed['mensaje']
                : 'Transaccion no aprobada' . ($parsed['cod_resp_desc'] ? ': ' . $parsed['cod_resp_desc'] : '');
        }

        // ===== 5) CACHE DE REVERSO (si aplica) =====
        // Solo cacheamos para operaciones que PUEDEN ser reversadas:
        //   - PP corriente
        //   - PP diferido (todos los subtipos)
        //   - RA reimpresion
        // Y solo si la transaccion fue APROBADA o tuvo TIMEOUT (ahi es CRITICO
        // mandar reverso porque no sabemos si el switch acepto o no).
        $op = $extra['operacion'] ?? '';
        $debeGuardarReverso = (
            in_array($op, [
                'corriente',
                'diferido_normal_con',
                'diferido_gracia_con',
                'diferido_mes_a_mes_con',
                'diferido_normal_sin',
                'diferido_gracia_sin',
                'diferido_mes_a_mes_sin',
                'diferido_especial_sin',
                'reimpresion'
            ])
            && ($aprobada || in_array($parsed['cod_resp'], ['TO', '@B']))
        );
        if ($debeGuardarReverso) {
            // TramaCache::storeReverso sobreescribe char[7] = '4' y guarda en cache.
            TramaCache::storeReverso(config('pinpad.tid'), $trama);
            // Flag para que el frontend sepa que hay un reverso pendiente.
            $data['reverso_cacheado'] = true;
        }

        // RespuestaApi::returnResultado ensambla el JSON final con code, status,
        // message, icon, color, data — el formato standard del proyecto.
        return RespuestaApi::returnResultado($estado, $msg, $data);
    }
}
