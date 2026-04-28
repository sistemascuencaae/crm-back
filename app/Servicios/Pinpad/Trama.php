<?php

namespace App\Servicios\Pinpad;

use App\Servicios\Pinpad\CifradoTramas;

/**
 * Constructor / parser de tramas Pin Pad Medianet.
 *
 * Tipos de mensaje (idMsj) extraidos del bytecode oficial:
 *   PP = Proceso de Pago         (cobro corriente, diferido, anulacion, reverso, maxidolar)
 *   CP = Cambio de Parametros    (configuracion de red del Pin Pad)
 *   PC = Proceso de Control      (cierre de lote)
 *   CT = Cierre de Turno         (operacion sin parametros)
 *   LT = Lectura de Tarjeta      (offline, opcional con monto)
 *   RA = Reimpresion / Re-Auth   (re-imprimir o re-autorizar por serial)
 *
 * Codigos comunes (de los radio buttons / userData del FXML):
 *   FILLER (adquirente):  Admon=0  Datafast=1  Medianet=2  Austro=3
 *   PP TXN:               Corriente=01  Diferido=02  Anulacion=03  Reverso=04  Maxidolar=07
 *   PP MOD (corriente):   00
 *   PP MOD (diferido):    01..06,08 segun tipo (Normal/Gracia/Mes/Especial x Con/Sin interes)
 */
final class Trama
{
    // ============== HELPERS BASICOS ==============

    /** Antepone "talla" = dechex(longitud_cuerpo). 4 chars hex. */
    public static function withLengthPrefix(string $body): string
    {
        $lenHex = str_pad(strtolower(dechex(strlen($body))), 4, '0', STR_PAD_LEFT);
        return $lenHex . $body;
    }

    public static function ensurePrefix(string $trama): string
    {
        if (strlen($trama) < 4) return self::withLengthPrefix($trama);
        $maybeLen = @hexdec(substr($trama, 0, 4));
        if ($maybeLen > 0 && strlen($trama) === $maybeLen + 4) {
            return $trama;
        }
        return self::withLengthPrefix($trama);
    }

    /** Centavos a string 12 chars '0' a la izquierda. */
    private static function amountCent($n): string
    {
        $i = (int) round((float)$n);
        if ($i < 0) $i = 0;
        return str_pad((string)$i, 12, '0', STR_PAD_LEFT);
    }

    /** 12 chars en blanco si vacio/null/0; numerico si tiene valor. */
    private static function amountOrBlank($n): string
    {
        if ($n === null || $n === '' || (int)round((float)$n) === 0) {
            return str_repeat(' ', 12);
        }
        return self::amountCent($n);
    }

    private static function pad(string $s, int $n, string $ch, int $type): string
    {
        if (strlen($s) > $n) $s = substr($s, 0, $n);
        return str_pad($s, $n, $ch, $type);
    }

    private static function padR(string $s, int $n): string
    {
        return self::pad($s, $n, ' ', STR_PAD_RIGHT);
    }

    private static function padL0(string $s, int $n): string
    {
        return self::pad($s, $n, '0', STR_PAD_LEFT);
    }

    // ============== PP - Proceso de Pago ==============

    /**
     * Mapeo de modalidades a TXN/MOD.
     * Para diferidos, $diferidoTipo escoge el subtipo (01-08).
     */
    public const PP_MODS = [
        'corriente'                  => ['txn' => '01', 'mod' => '00'],
        'diferido_normal_con'        => ['txn' => '02', 'mod' => '01'],
        'diferido_gracia_con'        => ['txn' => '02', 'mod' => '02'],
        'diferido_mes_a_mes_con'     => ['txn' => '02', 'mod' => '03'],
        'diferido_especial_con'      => ['txn' => '02', 'mod' => '08'],
        'diferido_normal_sin'        => ['txn' => '02', 'mod' => '04'],
        'diferido_gracia_sin'        => ['txn' => '02', 'mod' => '05'],
        'diferido_mes_a_mes_sin'     => ['txn' => '02', 'mod' => '06'],
        'diferido_especial_sin'      => ['txn' => '02', 'mod' => '08'],
        'anulacion'                  => ['txn' => '03', 'mod' => '00'],
        'reverso'                    => ['txn' => '04', 'mod' => '00'],
        'maxidolar_consulta'         => ['txn' => '07', 'mod' => '00'],
        'maxidolar_pago'             => ['txn' => '07', 'mod' => '01'],
    ];

    /**
     * Builder unificado para PP (Pinpad Exclusivo).
     *
     * Layout (212 bytes cuerpo + 4 prefijo = 216):
     *   [TIPO:2][TXN:2][FILLER:1][MOD:2][PERIODO:2][GRACIA:2][" ":1]
     *   [TOTAL:12][BASE15:12][BASE0:12][IVA:12]
     *   [SERVICIO:12 o 12sp][PROPINA:12 o 12sp][FIJO:12 o 12sp]
     *   [REF:6,'0'][TIME:6 HHmmss][DATE:8 yyyyMMdd][PLAZO:6,'0']
     *   [MID:15][TID:8][CIDTERM:15][SPACES:20][HASH:32]
     */
    public static function buildPp(string $modalidad, array $p): string
    {
        if (!isset(self::PP_MODS[$modalidad])) {
            throw new \InvalidArgumentException("Modalidad PP invalida: $modalidad");
        }
        $cfg = self::PP_MODS[$modalidad];

        $tipo    = 'PP';
        $txn     = $p['txn']     ?? $cfg['txn'];
        $filler  = $p['filler']  ?? '2';   // 2 = Medianet
        $mod     = $p['mod']     ?? $cfg['mod'];

        // Diferido usa periodo (plazo a cuotas) y gracia (meses gracia) en el header
        $isDiferido = str_starts_with($modalidad, 'diferido_');
        $periodo = $p['periodo'] ?? ($isDiferido ? self::padL0((string)($p['plazo']  ?? '0'), 2) : '00');
        $gracia  = $p['gracia']  ?? ($isDiferido ? self::padL0((string)($p['gracia_meses'] ?? '0'), 2) : '00');

        // En anulacion/reverso los montos van en cero (numerico).
        $isCancel = in_array($modalidad, ['anulacion', 'reverso']);
        $totalRaw  = $isCancel ? 0 : (float)($p['total']  ?? 0);
        $base15Raw = $isCancel ? 0 : (float)($p['base15'] ?? 0);
        $base0Raw  = $isCancel ? 0 : (float)($p['base0']  ?? 0);
        $ivaRaw    = $isCancel ? 0 : (float)($p['iva']    ?? 0);

        $total  = self::amountCent($totalRaw  * 100);
        $base15 = self::amountCent($base15Raw * 100);
        $base0  = self::amountCent($base0Raw  * 100);
        $iva    = self::amountCent($ivaRaw    * 100);

        // Servicio/propina/fijo: blanco por defecto, numerico si pasan valor > 0
        $servicio = self::amountOrBlank(isset($p['servicio']) ? ((float)$p['servicio'] * 100) : null);
        $propina  = self::amountOrBlank(isset($p['propina'])  ? ((float)$p['propina']  * 100) : null);
        $fijo     = self::amountOrBlank(isset($p['fijo'])     ? ((float)$p['fijo']     * 100) : null);

        $ref   = self::padL0((string)($p['referencia'] ?? '0'), 6);
        $time  = $p['time']  ?? date('His');
        $date  = $p['date']  ?? date('Ymd');
        $plazo = self::padL0((string)($p['plazo']      ?? '0'), 6);

        $mid     = self::padR($p['mid'] ?? '', 15);
        $tid     = self::padR($p['tid'] ?? '', 8);
        $cidTerm = self::padR($p['cid_terminal'] ?? '', 15);

        $body  = $tipo . $txn . $filler . $mod . $periodo . $gracia . ' ';
        $body .= $total . $base15 . $base0 . $iva . $servicio . $propina . $fijo;
        $body .= $ref . $time . $date . $plazo;
        $body .= $mid . $tid . $cidTerm;
        $body .= str_repeat(' ', 20);
        $body .= CifradoTramas::getHash();

        return self::withLengthPrefix($body);
    }

    /** Atajo: cobro corriente (lo mas usado) */
    public static function buildPinpadExclusivoCorriente(array $p): string
    {
        return self::buildPp('corriente', $p);
    }

    // ============== CT - Cierre de Turno ==============

    /**
     * Trama mas simple: solo idMsj + HASH.
     * Body = "CT" + 32 chars hex hash = 34 chars.
     */
    public static function buildCierreTurno(): string
    {
        $body = 'CT' . CifradoTramas::getHash();
        return self::withLengthPrefix($body);
    }

    // ============== LT - Lectura de Tarjeta ==============

    /**
     * Trama LT - Lectura/Consulta de Tarjeta Previo al Proceso de Pago.
     * Layout per manual (pag 3):
     *   "LT" + Monto Total (12 N, ceros izq) + HASH(32)
     *
     * Per manual el monto es REQUERIDO. Si no envias, va 12 ceros.
     *
     * @param float|null $amount Monto en dolares (null = 0)
     */
    public static function buildLecturaTarjeta(?float $amount = null): string
    {
        $body = 'LT';
        $body .= self::amountCent(($amount ?? 0) * 100);   // 12 chars siempre
        $body .= CifradoTramas::getHash();
        return self::withLengthPrefix($body);
    }

    // ============== CP - Cambio de Parametros ==============

    /**
     * Trama CP - Configuración de Datos de red del Pinpad.
     * Layout per manual (pag 14-15):
     *   "CP" + IP(15) + Mask(15) + Gateway(15)
     *        + IpHost1(15) + PortHost1(6)
     *        + IpAltHost1(15) + PortAltHost1(6)
     *        + IpHost2(15) + PortHost2(6)
     *        + IpAltHost2(15) + PortAltHost2(6)
     *        + PuertoEscucha(6 N, ceros izq)
     *        + HASH(32)
     *
     * Per manual, TODOS los IPs/puertos son AN justificados con BLANCOS a la
     * derecha (no zeros). Solo Puerto Escucha es N con ceros izq.
     */
    public static function buildCambioParametros(array $p): string
    {
        $ip      = self::padR($p['ip']      ?? '', 15);
        $mask    = self::padR($p['mask']    ?? '', 15);
        $gateway = self::padR($p['gateway'] ?? '', 15);

        // Puertos AN: blancos a la derecha (per manual)
        $ipHost1      = self::padR($p['ip_host1']      ?? '', 15);
        $portHost1    = self::padR((string)($p['port_host1']     ?? ''), 6);
        $ipAltHost1   = self::padR($p['ip_alt_host1']  ?? '', 15);
        $portAltHost1 = self::padR((string)($p['port_alt_host1'] ?? ''), 6);
        $ipHost2      = self::padR($p['ip_host2']      ?? '', 15);
        $portHost2    = self::padR((string)($p['port_host2']     ?? ''), 6);
        $ipAltHost2   = self::padR($p['ip_alt_host2']  ?? '', 15);
        $portAltHost2 = self::padR((string)($p['port_alt_host2'] ?? ''), 6);

        // Puerto Escucha: N (numerico) con ceros izq
        $listeningPort = self::padL0((string)($p['listening_port'] ?? '0'), 6);

        $body  = 'CP';
        $body .= $ip . $mask . $gateway;
        $body .= $ipHost1 . $portHost1 . $ipAltHost1 . $portAltHost1;
        $body .= $ipHost2 . $portHost2 . $ipAltHost2 . $portAltHost2;
        $body .= $listeningPort;
        $body .= CifradoTramas::getHash();

        return self::withLengthPrefix($body);
    }

    // ============== PC - Proceso de Control (cierre de lote) ==============

    /**
     * Trama PC - Proceso de Control (cierre de lote).
     * Layout per manual (pag 13):
     *   "PC" + NumLote(6 N, ceros izq) + Secuencial(6 N, ceros izq)
     *        + Filler(12 N, blancos)
     *        + MID(15 AN, blancos der) + TID(8 AN)
     *        + Filler(23 AN, blancos)
     *        + CID(15 AN, blancos der)
     *        + RedActiva(1 AN: "2"=Medianet)
     *        + HASH(32)
     *
     * Total cuerpo: 2+6+6+12+15+8+23+15+1+32 = 120 chars
     */
    public static function buildProcesoControl(array $p): string
    {
        $batch     = self::padL0((string)($p['batch']     ?? '0'), 6);
        $reference = self::padL0((string)($p['reference'] ?? '0'), 6);

        $filler12 = str_repeat(' ', 12);
        $mid      = self::padR($p['mid'] ?? '', 15);
        $tid      = self::padR($p['tid'] ?? '', 8);                  // AN, blancos der
        $filler23 = str_repeat(' ', 23);
        $cidTerm  = self::padR($p['cid_terminal'] ?? '', 15);

        // Codigo de Red Activa: 1=Datafast, 2=Medianet, 3=Austro
        $redActiva = $p['red_activa'] ?? '2';

        $body  = 'PC';
        $body .= $batch . $reference;
        $body .= $filler12;
        $body .= $mid . $tid . $filler23 . $cidTerm;
        $body .= $redActiva;
        $body .= CifradoTramas::getHash();

        return self::withLengthPrefix($body);
    }

    // ============== RA - Reimpresion / Re-Autorizacion ==============

    /**
     * Trama RA - Avance en Efectivo (Cash Advance).
     * Layout extraido de model.f.b() + viewmodel.f.a() del bytecode oficial.
     *
     * Campos (cuerpo):
     *   "RA" (2)
     *   + TXN (raw, 2)        Corriente/Diferido="08"  Anulacion="03"  Reverso="04"
     *   + filler (4 sp)
     *   + MOD (2)             "00" si no diferido; subtipo si diferido (01-08)
     *   + plazo (2 '0' L)
     *   + gracia (2 '0' L)
     *   + monto base (12)
     *   + filler (36 sp)
     *   + referencia (6 '0' L)
     *   + TIME (6 HHmmss)
     *   + DATE (8 yyyyMMdd)
     *   + adquirente (6 '0' L)  default "000000" (Admon=0, DF=1, MN=2, Austro=3)
     *   + filler (44 sp)
     *   + serial (15 sp R)
     *   + filler (34 sp)
     *   + HASH(32)
     * Total cuerpo: 213 chars
     *
     * @param string $modalidad corriente|diferido|anulacion|reverso
     */
    public const RA_TXN_CODES = [
        'corriente'  => '08',
        'diferido'   => '08',
        'anulacion'  => '03',
        'reverso'    => '04',
    ];

    public static function buildReimpresion(string $modalidad, array $p): string
    {
        if (!isset(self::RA_TXN_CODES[$modalidad])) {
            throw new \InvalidArgumentException("Modalidad RA invalida: $modalidad");
        }

        $txn = self::RA_TXN_CODES[$modalidad];

        // MOD: para diferido se elige subtipo (01..08); para resto "00".
        $modCodes = [
            'normal_con'        => '01',
            'gracia_con'        => '02',
            'mes_a_mes_con'     => '03',
            'especial_con'      => '08',
            'normal_sin'        => '04',
            'gracia_sin'        => '05',
            'mes_a_mes_sin'     => '06',
            'especial_sin'      => '08',
        ];
        $mod = '00';
        if ($modalidad === 'diferido' && !empty($p['diferido_tipo']) && isset($modCodes[$p['diferido_tipo']])) {
            $mod = $modCodes[$p['diferido_tipo']];
        }

        $isCancel = in_array($modalidad, ['anulacion', 'reverso']);
        $serial   = self::padR($p['serial'] ?? '', 15);

        $plazo  = self::padL0((string)($p['plazo']  ?? '0'), 2);
        $gracia = self::padL0((string)($p['gracia'] ?? '0'), 2);

        $totalCents = $isCancel ? 0 : (int) round((float)($p['total'] ?? 0) * 100);
        $monto = self::amountCent($totalCents);

        $referencia = self::padL0((string)($p['referencia'] ?? '0'), 6);
        $time = $p['time'] ?? date('His');
        $date = $p['date'] ?? date('Ymd');

        // Adquirente code (en el bytecode se setea con c.g.b.trim() = "0" Admon)
        // Por defecto enviamos "000000" como en la libreria.
        $adquirenteCode = self::padL0((string)($p['adquirente_code'] ?? '0'), 6);

        $body  = 'RA';
        $body .= $txn;
        $body .= str_repeat(' ', 4);                // filler 4
        $body .= $mod;
        $body .= $plazo;
        $body .= $gracia;
        $body .= $monto;
        $body .= str_repeat(' ', 36);               // filler 36
        $body .= $referencia;
        $body .= $time;
        $body .= $date;
        $body .= $adquirenteCode;
        $body .= str_repeat(' ', 44);               // filler 44
        $body .= $serial;
        $body .= str_repeat(' ', 34);               // filler 34
        $body .= CifradoTramas::getHash();

        return self::withLengthPrefix($body);
    }

    // ============== PARSE RESPONSE ==============

    public static function parseResponse(string $resp): array
    {
        $out = [
            'raw'           => $resp,
            'len_hex'       => null,
            'len'           => null,
            'body'          => null,
            'tipo'          => null,
            'mensaje'       => null,
            'hash_recibido' => null,
            'cod_resp'      => null,
            'cod_resp_desc' => null,
        ];
        if (strlen($resp) < 4) {
            $out['error'] = 'Respuesta demasiado corta';
            return $out;
        }
        $out['len_hex'] = substr($resp, 0, 4);
        $out['len']    = hexdec($out['len_hex']);
        $body          = substr($resp, 4, $out['len']);

        if (strlen($body) >= 32 && preg_match('/^[0-9A-Fa-f]{32}$/', substr($body, -32))) {
            $out['hash_recibido'] = substr($body, -32);
            $body = substr($body, 0, -32);
        }
        $out['body'] = $body;
        $out['tipo'] = trim(substr($body, 0, 4));

        $upper = strtoupper($body);
        // Patrones de los mensajes humanos del manual + mensajes vistos en respuestas reales
        $patrones = [
            '00' => ['/AUTORIZACION\s+OK/', '/APROB(ADA|ADO)/', '/AUTORIZAD[AO]/'],
            '01' => ['/ERROR\s+EN\s+TRAMA/'],
            '02' => ['/ERR\.?\s+CONEXION\s+PINPAD/'],
            '03' => ['/ERROR\s+SEGURIDAD/'],
            '20' => ['/ERROR\s+DURANTE\s+PROCESO/'],
            'TO' => ['/TIMEOUT/', '/TIME\s*OUT/'],
            'ER' => ['/ERROR\s+CONEXION/'],
            '05' => ['/NEGAD[AO]/', '/RECHAZAD[AO]/'],
            '13' => ['/MONTO\s+INVALIDO/'],
            '14' => ['/TARJETA\s+INVALIDA/'],
            '30' => ['/ERROR\s+DE\s+FORMATO/'],
            '51' => ['/FONDOS\s+INSUFICIENTES/'],
            '54' => ['/TARJETA\s+EXPIRADA/'],
            '@1' => ['/LONGITUD/'],
            '@B' => ['/AUTORIZADOR\s+NO/'],
        ];
        foreach ($patrones as $cod => $regexes) {
            foreach ($regexes as $rx) {
                if (preg_match($rx, $upper, $m)) {
                    $out['cod_resp'] = $cod;
                    $out['mensaje']  = trim($m[0]);
                    break 2;
                }
            }
        }

        if ($out['cod_resp'] === null) {
            $candidato = trim(substr($body, 4, 2));
            if (preg_match('/^[0-9A-Z@]{2}$/i', $candidato)) {
                $out['cod_resp'] = $candidato;
            }
        }

        $out['cod_resp_desc'] = $out['cod_resp']
            ? self::descripcionCodigoRespuesta($out['cod_resp'])
            : null;

        return $out;
    }

    public static function descripcionCodigoRespuesta(string $cod): string
    {
        // Tabla extraida del manual oficial Mensajeria Caja Pinpad v1.4
        // mas los codigos del archivo CatalogoResp.txt
        static $tabla = [
            // Codigos del Pin Pad (locales, manual pag 3-15)
            '00' => 'Ejecucion Exitosa / Aprobada',
            '01' => 'Error en Trama',
            '02' => 'Error conexion Pinpad / Realiza Inicio de Dia',
            '03' => 'Error de Seguridad',
            '20' => 'Error durante Proceso',
            'TO' => 'Timeout (requiere reverso automatico)',
            'ER' => 'Error conexion Pinpad',
            // Codigos del autorizador (CatalogoResp.txt)
            '04' => 'Retener tarjeta',
            '05' => 'Negada',
            '06' => 'Error',
            '08' => 'Aprobada (VIP)',
            '12' => 'Transaccion invalida',
            '13' => 'Monto invalido',
            '14' => 'Numero de tarjeta invalido',
            '30' => 'Error de formato',
            '41' => 'Retener tarjeta extraviada',
            '43' => 'Retener tarjeta robada',
            '51' => 'Fondos insuficientes',
            '54' => 'Tarjeta expirada',
            '55' => 'PIN invalido',
            '57' => 'Transaccion no permitida al emisor',
            '58' => 'Transaccion no permitida al adquirente',
            '61' => 'Excede monto limite',
            '76' => 'Imposible localizar mensaje original',
            '91' => 'Emisor no disponible',
            '96' => 'Mal funcionamiento del sistema',
            '@1' => 'Error, longitud de trama no coincide',
            '@B' => 'Time Out, autorizador no responde',
            '@F' => 'BIN no existe',
        ];
        return $tabla[$cod] ?? 'Codigo desconocido';
    }
}
