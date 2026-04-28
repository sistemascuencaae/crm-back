<?php

namespace App\Http\Controllers\pinpad;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\Pinpad\CifradoTramas;
use App\Servicios\Pinpad\Conexion;
use App\Servicios\Pinpad\Trama;
use App\Servicios\Pinpad\TramaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PinpadController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api');  // habilita cuando lo amerite
    }

    // ============================================================
    // UTILIDADES
    // ============================================================

    /** GET /pinpad/probe - verifica conexion TCP al Pin Pad */
    public function probe()
    {
        try {
            $ip   = config('pinpad.ip');
            $port = (int) config('pinpad.port');
            $ok = Conexion::probe($ip, $port, 3000);
            $data = [
                'pinpad'    => "$ip:$port",
                'reachable' => $ok,
                'host_medianet' => config('pinpad.host_medianet') . ' (uso interno del Pin Pad)',
            ];
            $estado = $ok ? 'success' : 'error';
            $msg = $ok ? "Pin Pad accesible en $ip:$port" : "Pin Pad NO accesible en $ip:$port";
            return response()->json(RespuestaApi::returnResultado($estado, $msg, $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    /** GET /pinpad/hash - genera y valida un hash 3DES */
    public function hash()
    {
        try {
            $h = CifradoTramas::getHash();
            return response()->json(RespuestaApi::returnResultado('success', 'Hash generado', [
                'hash'   => $h,
                'len'    => strlen($h),
                'valido' => CifradoTramas::validateHash($h),
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    /** POST /pinpad/raw - envia trama YA construida (debug) */
    public function raw(Request $req)
    {
        try {
            $req->validate(['trama' => 'required|string|min:5']);
            $trama = Trama::ensurePrefix($req->input('trama'));
            return response()->json($this->enviarYParsear($trama));
        } catch (\Throwable $th) {
            Log::error('Pinpad/raw: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // PP - PROCESO DE PAGO
    // ============================================================

    /** POST /pinpad/cobrar - PP Corriente */
    public function cobrar(Request $req)
    {
        return $this->enviarPp('corriente', $req, [
            'total'    => 'required|numeric|min:0.01',
            'base15'   => 'required|numeric|min:0',
            'base0'    => 'required|numeric|min:0',
            'iva'      => 'required|numeric|min:0',
            'servicio' => 'nullable|numeric|min:0',
            'propina'  => 'nullable|numeric|min:0',
        ]);
    }

    /** POST /pinpad/diferido - PP Diferido (cuotas) */
    public function diferido(Request $req)
    {
        $modalidad = $req->input('modalidad', 'diferido_normal_con');
        if (!str_starts_with($modalidad, 'diferido_')) {
            return response()->json(RespuestaApi::returnResultado('error', 'modalidad debe ser diferido_*', null));
        }
        return $this->enviarPp($modalidad, $req, [
            'modalidad'    => 'required|string|in:' . implode(',', array_keys(Trama::PP_MODS)),
            'total'        => 'required|numeric|min:0.01',
            'base15'       => 'required|numeric|min:0',
            'base0'        => 'required|numeric|min:0',
            'iva'          => 'required|numeric|min:0',
            'plazo'        => 'required|integer|min:1|max:99',
            'gracia_meses' => 'nullable|integer|min:0|max:99',
            'servicio'     => 'nullable|numeric|min:0',
            'propina'      => 'nullable|numeric|min:0',
        ]);
    }

    /** POST /pinpad/anular - PP Anulacion */
    public function anular(Request $req)
    {
        return $this->enviarPp('anulacion', $req, [
            'referencia' => 'required|string',
        ]);
    }

    /**
     * POST /pinpad/reverso - PP Reverso (rollback de la ultima transaccion).
     * Si hay una trama de reverso cacheada (de la ultima PP/RA exitosa) se
     * envia esa misma; si no, se arma una nueva.
     */
    public function reverso(Request $req)
    {
        try {
            $tid = config('pinpad.tid');
            $cached = TramaCache::getReverso($tid);

            if ($cached) {
                $resp = $this->enviarYParsear($cached, ['operacion' => 'reverso', 'fuente' => 'cache']);
                // Si el reverso fue exitoso, limpiamos la cache (no hay nada que reversar dos veces)
                if (in_array($resp['data']['cod_resp'] ?? null, ['00', '08'])) {
                    TramaCache::clearReverso($tid);
                }
                return response()->json($resp);
            }

            // Fallback: armar reverso desde cero (sin datos de transaccion previa)
            return $this->enviarPp('reverso', $req, []);
        } catch (\Throwable $th) {
            \Log::error('Pinpad/reverso: ' . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    /** GET /pinpad/reverso-disponible - chequea si hay un reverso pendiente */
    public function reversoDisponible()
    {
        $tid = config('pinpad.tid');
        return response()->json(RespuestaApi::returnResultado('success', 'Estado del cache', [
            'disponible' => TramaCache::hasReverso($tid),
            'tid'        => $tid,
        ]));
    }

    /** POST /pinpad/maxidolar - PP Maxidolar */
    public function maxidolar(Request $req)
    {
        $modalidad = $req->input('modalidad', 'maxidolar_consulta');
        return $this->enviarPp($modalidad, $req, [
            'modalidad' => 'required|string|in:maxidolar_consulta,maxidolar_pago',
            'total'     => 'nullable|numeric|min:0',
        ]);
    }

    /** Helper compartido por todas las modalidades PP */
    private function enviarPp(string $modalidad, Request $req, array $rules)
    {
        try {
            $data = $rules ? $req->validate($rules) : $req->all();

            $payload = array_merge($data, [
                'mid'          => config('pinpad.mid'),
                'tid'          => config('pinpad.tid'),
                'cid_terminal' => config('pinpad.cid_terminal'),
            ]);

            $trama = Trama::buildPp($modalidad, $payload);
            return response()->json($this->enviarYParsear($trama, ['operacion' => $modalidad]));
        } catch (\Throwable $th) {
            Log::error("Pinpad/$modalidad: " . $th->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $th->getMessage(), null));
        }
    }

    // ============================================================
    // CT - CIERRE DE TURNO
    // ============================================================

    /** POST /pinpad/cierre-turno */
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

    /** POST /pinpad/lectura - lectura sin/con monto */
    public function lectura(Request $req)
    {
        try {
            $data = $req->validate([
                'monto' => 'nullable|numeric|min:0',
            ]);
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

    /** POST /pinpad/cambio-parametros */
    public function cambioParametros(Request $req)
    {
        try {
            $data = $req->validate([
                'ip'              => 'required|ip',
                'mask'            => 'required|string',
                'gateway'         => 'required|ip',
                'listening_port'  => 'required|integer',
                'ip_host1'        => 'nullable|ip',
                'port_host1'      => 'nullable|integer',
                'ip_alt_host1'    => 'nullable|ip',
                'port_alt_host1'  => 'nullable|integer',
                'ip_host2'        => 'nullable|ip',
                'port_host2'      => 'nullable|integer',
                'ip_alt_host2'    => 'nullable|ip',
                'port_alt_host2'  => 'nullable|integer',
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

    /** POST /pinpad/cierre-lote */
    public function cierreLote(Request $req)
    {
        try {
            $data = $req->validate([
                'batch'     => 'nullable|integer',
                'reference' => 'nullable|integer',
            ]);
            $payload = array_merge($data, [
                'mid'          => config('pinpad.mid'),
                'tid'          => config('pinpad.tid'),
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
                'modalidad'      => 'required|string|in:corriente,diferido,anulacion,reverso',
                'serial'         => 'required|string|min:10|max:15|regex:/^[A-Za-z0-9]+$/',
                'base'           => 'nullable|numeric|min:0',
                'total'          => 'nullable|numeric|min:0',
                'referencia'     => 'nullable|string|max:6',
                'plazo'          => 'nullable|integer|min:0|max:99',
                'gracia'         => 'nullable|integer|min:0|max:99',
                'diferido_tipo'  => 'nullable|string|in:normal_con,gracia_con,mes_a_mes_con,especial_con,normal_sin,gracia_sin,mes_a_mes_sin,especial_sin',
                'adquirente_code'=> 'nullable|string|max:6',
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

    private function enviarYParsear(string $trama, array $extra = []): array
    {
        $ip      = config('pinpad.ip');
        $port    = (int) config('pinpad.port');
        $timeout = (int) config('pinpad.timeout_ms');

        Log::info("Pinpad -> $ip:$port (" . strlen($trama) . " bytes): $trama");

        $resp = Conexion::sendRecv($ip, $port, $trama, $timeout);
        $parsed = Trama::parseResponse($resp);

        Log::info('Pinpad <- ' . json_encode($parsed));

        $data = array_merge($extra, [
            'trama_enviada'  => $trama,
            'trama_recibida' => $parsed['raw'],
            'longitud'       => $parsed['len'],
            'tipo'           => $parsed['tipo'],
            'cuerpo'         => $parsed['body'],
            'mensaje_pinpad' => $parsed['mensaje'],
            'hash_recibido'  => $parsed['hash_recibido'],
            'cod_resp'       => $parsed['cod_resp'],
            'cod_resp_desc'  => $parsed['cod_resp_desc'],
        ]);

        $aprobada = in_array($parsed['cod_resp'], ['00', '08']);
        $estado = $aprobada ? 'success' : 'error';
        if ($aprobada) {
            $msg = 'Transaccion aprobada';
        } else {
            $msg = $parsed['mensaje']
                ? 'Pinpad: ' . $parsed['mensaje']
                : 'Transaccion no aprobada' . ($parsed['cod_resp_desc'] ? ': ' . $parsed['cod_resp_desc'] : '');
        }

        // Si la operacion fue PP/RA corriente o diferido y resulto APROBADA o
        // hubo TIMEOUT, guardamos la version reverso en cache para poder revertirla.
        $op = $extra['operacion'] ?? '';
        $debeGuardarReverso = (
            in_array($op, ['corriente', 'diferido_normal_con', 'diferido_gracia_con',
                           'diferido_mes_a_mes_con', 'diferido_especial_con',
                           'diferido_normal_sin', 'diferido_gracia_sin',
                           'diferido_mes_a_mes_sin', 'diferido_especial_sin',
                           'reimpresion'])
            && ($aprobada || in_array($parsed['cod_resp'], ['TO', '@B']))
        );
        if ($debeGuardarReverso) {
            TramaCache::storeReverso(config('pinpad.tid'), $trama);
            $data['reverso_cacheado'] = true;
        }

        return RespuestaApi::returnResultado($estado, $msg, $data);
    }
}
