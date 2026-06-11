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

    /**
     * withLengthPrefix() - antepone la "talla" (longitud del cuerpo en hex).
     *
     * Ejemplo: si $body mide 212 chars:
     *   dechex(212)             => "d4"
     *   str_pad("d4", 4, '0')   => "00d4"
     *   resultado: "00d4" + body
     *
     * El Pin Pad lee los primeros 4 chars como hex para saber cuanta data
     * leer despues. Es el primer campo de toda trama.
     */
    public static function withLengthPrefix(string $body): string
    {
        // dechex(int) -> string hex en minusculas
        // str_pad rellena con '0' a la izquierda hasta 4 chars
        $lenHex = str_pad(strtolower(dechex(strlen($body))), 4, '0', STR_PAD_LEFT);
        return $lenHex . $body;
    }

    /**
     * ensurePrefix() - si la trama YA trae prefijo correcto, la deja como esta;
     * si no, la prefija. Util para el endpoint /pinpad/raw donde el cajero
     * podria pegar la trama con o sin prefijo.
     */
    public static function ensurePrefix(string $trama): string
    {
        // Trama muy corta = no puede tener prefijo, asumimos que es solo cuerpo
        if (strlen($trama) < 4) return self::withLengthPrefix($trama);

        // Intentamos parsear los 4 primeros chars como hex.
        // El @ silencia warnings si no son hex validos.
        $maybeLen = @hexdec(substr($trama, 0, 4));

        // Si el numero parseado coincide con la longitud del resto, ya tenia prefijo.
        if ($maybeLen > 0 && strlen($trama) === $maybeLen + 4) {
            return $trama;
        }
        // No tenia prefijo (o estaba mal): lo agregamos.
        return self::withLengthPrefix($trama);
    }

    /**
     * amountCent() - convierte centavos numericos a string de 12 chars padded.
     *
     * Ejemplo:
     *   amountCent(112)  => "000000000112"  (= $1.12)
     *   amountCent(0)    => "000000000000"
     *
     * Manual oficial: "10 enteros 2 decimales (sin puntuacion), justificados
     * con ceros a la izquierda".
     */
    private static function amountCent($n): string
    {
        // round() para manejar floats con error de redondeo (ej 1.999... -> 2)
        $i = (int) round((float)$n);
        // Defensiva: nunca aceptar negativos
        if ($i < 0) $i = 0;
        return str_pad((string)$i, 12, '0', STR_PAD_LEFT);
    }

    /**
     * amountOrBlank() - devuelve 12 espacios cuando el monto es 0/null/vacio,
     * o el monto numerico si tiene valor real.
     *
     * Manual: campos como "Servicio", "Propina", "Fijo" deben ir en BLANCO
     * cuando no aplican (no en cero), porque el Pin Pad usa eso para
     * distinguir "no hay valor" de "valor cero".
     */
    private static function amountOrBlank($n): string
    {
        if ($n === null || $n === '' || (int)round((float)$n) === 0) {
            return str_repeat(' ', 12);  // 12 espacios
        }
        return self::amountCent($n);
    }

    /**
     * pad() - rellena (o trunca) un string a la longitud exacta.
     *
     * @param string $ch    char de relleno (puede ser ' ' o '0')
     * @param int    $type  STR_PAD_LEFT (rellena a izq) o STR_PAD_RIGHT
     */
    private static function pad(string $s, int $n, string $ch, int $type): string
    {
        // Si excede el largo permitido, lo truncamos para no romper el layout.
        if (strlen($s) > $n) $s = substr($s, 0, $n);
        return str_pad($s, $n, $ch, $type);
    }

    /** Atajo: rellena con espacios a la derecha (campos AN del manual). */
    private static function padR(string $s, int $n): string
    {
        return self::pad($s, $n, ' ', STR_PAD_RIGHT);
    }

    /** Atajo: rellena con '0' a la izquierda (campos N del manual). */
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
     * Genera la trama posicional de 212 bytes (cuerpo) que el Pin Pad espera.
     * Layout exacto del manual oficial v1.4 paginas 5-7:
     *
     *   [TIPO:2][TXN:2][FILLER:1][MOD:2][PERIODO:2][GRACIA:2][" ":1]   <- 12 (header)
     *   [TOTAL:12][BASE15:12][BASE0:12][IVA:12]                        <- 48 (montos numericos)
     *   [SERVICIO:12][PROPINA:12][FIJO:12]                             <- 36 (montos opcionales)
     *   [REF:6][TIME:6 HHmmss][DATE:8 yyyyMMdd][AUTH:6]                <- 26 (refs/timestamps)
     *   [MID:15][TID:8][CIDTERM:15]                                    <- 38 (identificadores)
     *   [SPACES:20]                                                    <- 20 (filler)
     *   [HASH:32 hex]                                                  <- 32 (3DES MAC)
     *                                                                  ----
     *                                                                  212 bytes
     *
     * Mas el prefijo de 4 chars hex con la longitud al inicio = 216 chars total.
     */
    public static function buildPp(string $modalidad, array $p): string
    {
        // Valida que la modalidad sea una de las soportadas (corriente,
        // diferido_*, anulacion, reverso, maxidolar_*).
        if (!isset(self::PP_MODS[$modalidad])) {
            throw new \InvalidArgumentException("Modalidad PP invalida: $modalidad");
        }
        // Recupera los codigos TXN y MOD para esta modalidad.
        $cfg = self::PP_MODS[$modalidad];

        // ===== HEADER (12 chars) =====

        // TIPO de mensaje (2 chars). Para Proceso de Pago siempre es "PP".
        $tipo    = 'PP';

        // TXN (2 chars) = codigo de transaccion:
        //   "01" = corriente
        //   "02" = diferido
        //   "03" = anulacion
        //   "04" = reverso
        //   "07" = maxidolar
        // El operador ?? deja que el caller lo override pasando 'txn' en $p.
        $txn     = $p['txn']     ?? $cfg['txn'];

        // FILLER (1 char) = codigo del adquirente:
        //   "0" = Admon Pinpad
        //   "1" = Datafast
        //   "2" = Medianet  <-- nuestro caso
        //   "3" = Austro
        $filler  = $p['filler']  ?? '2';

        // MOD (2 chars) = codigo de modalidad/diferido:
        //   "00" = corriente normal
        //   "01"-"08" = subtipos de diferido (con/sin interes, gracia, etc)
        $mod     = $p['mod']     ?? $cfg['mod'];

        // PERIODO y GRACIA (2 chars cada uno):
        // Solo aplican para diferidos. Para corriente van "00".
        // str_starts_with: PHP 8+, devuelve true si la cadena empieza con $needle.
        $isDiferido = str_starts_with($modalidad, 'diferido_');
        // padL0: rellena con '0' a la izquierda hasta el ancho deseado.
        $periodo = $p['periodo'] ?? ($isDiferido ? self::padL0((string)($p['plazo']  ?? '0'), 2) : '00');
        $gracia  = $p['gracia']  ?? ($isDiferido ? self::padL0((string)($p['gracia_meses'] ?? '0'), 2) : '00');

        // ===== MONTOS NUMERICOS (48 chars: 4 x 12) =====

        // Para anulacion y reverso, todos los montos van en CERO (no en blanco).
        // El switch real de Medianet usa esto para identificar el tipo.
        $isCancel = in_array($modalidad, ['anulacion', 'reverso']);

        // Casteamos a float ?? 0 para evitar problemas si no se pasaron.
        // Los montos vienen del frontend en DOLARES (ej: 1.12), pero la trama
        // los expresa en CENTAVOS sin decimales (ej: 000000000112).
        $totalRaw  = $isCancel ? 0 : (float)($p['total']  ?? 0);
        $base15Raw = $isCancel ? 0 : (float)($p['base15'] ?? 0);
        $base0Raw  = $isCancel ? 0 : (float)($p['base0']  ?? 0);
        $ivaRaw    = $isCancel ? 0 : (float)($p['iva']    ?? 0);

        // amountCent: $1.12 * 100 = 112 -> "000000000112" (12 chars padded).
        $total  = self::amountCent($totalRaw  * 100);
        $base15 = self::amountCent($base15Raw * 100);
        $base0  = self::amountCent($base0Raw  * 100);
        $iva    = self::amountCent($ivaRaw    * 100);

        // ===== MONTOS OPCIONALES (36 chars: 3 x 12) =====
        // Servicio, Propina y Fijo solo aplican para giros especificos
        // (restaurante, gasolinera). Si no aplican, van 12 ESPACIOS (no ceros).
        // amountOrBlank devuelve 12 espacios si el valor es null/0/'', sino numerico.
        $servicio = self::amountOrBlank(isset($p['servicio']) ? ((float)$p['servicio'] * 100) : null);
        $propina  = self::amountOrBlank(isset($p['propina'])  ? ((float)$p['propina']  * 100) : null);
        $fijo     = self::amountOrBlank(isset($p['fijo'])     ? ((float)$p['fijo']     * 100) : null);

        // ===== REFS Y TIMESTAMPS (26 chars: 6+6+8+6) =====

        // REF (6 chars, '0' a la izq) = secuencial transaccion para anulaciones,
        // "000000" para corriente.
        $ref   = self::padL0((string)($p['referencia'] ?? '0'), 6);

        // TIME (6 chars, formato HHmmss): "143025" = 14:30:25
        // date('His') de PHP da el formato exacto (24h).
        $time  = $p['time']  ?? date('His');

        // DATE (8 chars, formato yyyyMMdd): "20260428" = 2026-04-28
        // date('Ymd') de PHP. CRITICO: 4 digitos de anio, no 2.
        $date  = $p['date']  ?? date('Ymd');

        // AUTH/PLAZO (6 chars, '0' a la izq) = numero de autorizacion
        // (para anulaciones) o "000000" para corriente.
        $plazo = self::padL0((string)($p['plazo']      ?? '0'), 6);

        // ===== IDENTIFICADORES (38 chars: 15+8+15) =====

        // padR: rellena con espacios a la DERECHA hasta el ancho.
        // MID (15 chars): codigo del comercio asignado por el banco.
        $mid     = self::padR($p['mid'] ?? '', 15);
        // TID (8 chars exactos): identificador de la terminal/caja.
        $tid     = self::padR($p['tid'] ?? '', 8);
        // CID Terminal (15 chars): identificador del Pin Pad fisico (puede ir vacio).
        $cidTerm = self::padR($p['cid_terminal'] ?? '', 15);

        // ===== ENSAMBLE DEL CUERPO =====

        // Linea 1 (12 chars): header
        $body  = $tipo . $txn . $filler . $mod . $periodo . $gracia . ' ';
        // Linea 2 (84 chars): 7 montos x 12
        $body .= $total . $base15 . $base0 . $iva . $servicio . $propina . $fijo;
        // Linea 3 (26 chars): refs + timestamps
        $body .= $ref . $time . $date . $plazo;
        // Linea 4 (38 chars): MID + TID + CID
        $body .= $mid . $tid . $cidTerm;
        // Linea 5 (20 chars): filler de espacios (campo "Filler 20 AN" del manual)
        $body .= str_repeat(' ', 20);
        // Linea 6 (32 chars): HASH 3DES (16 random + 16 cifrados)
        $body .= CifradoTramas::getHash();

        // Total $body: 12 + 84 + 26 + 38 + 20 + 32 = 212 chars exactos.
        // withLengthPrefix antepone "00d4" (212 en hex con padding).
        return self::withLengthPrefix($body);
    }

    /**
     * buildPinpadExclusivoCorriente() - alias retro-compatible de buildPp('corriente').
     * Util si se quiere una API mas explicita que diga "cobro corriente".
     */
    public static function buildPinpadExclusivoCorriente(array $p): string
    {
        return self::buildPp('corriente', $p);
    }

    // ============== CT - Cierre/Consulta de Turno ==============

    /**
     * buildCierreTurno() - la trama mas simple del manual.
     *
     * Layout per manual pag 4:
     *   [length:4] + ["CT":2] + [HASH:32 hex]
     *   = 4 prefijo + 34 cuerpo = 38 chars total
     *
     * No requiere parametros: el Pin Pad sabe a que turno aplica por su
     * propio estado interno.
     */
    public static function buildCierreTurno(): string
    {
        // "CT" (2 chars) + hash 3DES (32 chars hex) = 34 chars cuerpo.
        $body = 'CT' . CifradoTramas::getHash();
        // Con el prefijo de 4 chars hex queda 38 chars en total.
        return self::withLengthPrefix($body);
    }

    // ============== LT - Lectura/Consulta de Tarjeta ==============

    /**
     * buildLecturaTarjeta() - lee tarjeta sin cobrar (consulta previa al pago).
     *
     * Layout per manual pag 3:
     *   ["LT":2] + [Monto Total:12 N] + [HASH:32]
     *   = 46 chars cuerpo
     *
     * El manual indica monto REQUERIDO. Si el caller no envia monto, va "000000000000".
     *
     * @param float|null $amount Monto en DOLARES (no centavos). null = 0.
     */
    public static function buildLecturaTarjeta(?float $amount = null): string
    {
        $body = 'LT';
        // Convertimos dolares a centavos (* 100) y formateamos a 12 chars.
        // ($amount ?? 0): si es null usa 0.
        $body .= self::amountCent(($amount ?? 0) * 100);
        // Hash al final, mismo patron que todas las tramas.
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
        // ===== Datos propios del Pin Pad (15 chars cada uno, AN, blancos der) =====
        $ip      = self::padR($p['ip']      ?? '', 15);  // IP que tendra el Pin Pad
        $mask    = self::padR($p['mask']    ?? '', 15);  // Mascara de subred
        $gateway = self::padR($p['gateway'] ?? '', 15);  // Gateway

        // ===== Hosts del switch (4 pares ip+puerto) =====
        // Cada Pin Pad puede tener configurados hasta 4 hosts:
        //   - Host1 Principal y Alterno (Red 1)
        //   - Host2 Principal y Alterno (Red 2)
        // Si no se quiere usar alguno, va en blanco (15 espacios para IP, 6 para puerto).
        // Manual: TODOS estos campos son AN (blancos a la derecha), incluidos
        // los puertos. NO usar zero-pad porque rompe el formato esperado.
        $ipHost1      = self::padR($p['ip_host1']      ?? '', 15);
        $portHost1    = self::padR((string)($p['port_host1']     ?? ''), 6);
        $ipAltHost1   = self::padR($p['ip_alt_host1']  ?? '', 15);
        $portAltHost1 = self::padR((string)($p['port_alt_host1'] ?? ''), 6);
        $ipHost2      = self::padR($p['ip_host2']      ?? '', 15);
        $portHost2    = self::padR((string)($p['port_host2']     ?? ''), 6);
        $ipAltHost2   = self::padR($p['ip_alt_host2']  ?? '', 15);
        $portAltHost2 = self::padR((string)($p['port_alt_host2'] ?? ''), 6);

        // ===== Puerto de Escucha (6 chars, N, ceros izq) =====
        // EXCEPCION: solo este puerto va con zero-pad (es el unico campo "N"
        // numerico estricto del CP, los demas puertos son AN).
        // Es el puerto donde el Pin Pad escuchara peticiones de la caja.
        $listeningPort = self::padL0((string)($p['listening_port'] ?? '0'), 6);

        // ===== Ensamble del cuerpo =====
        $body  = 'CP';                                                      // 2
        $body .= $ip . $mask . $gateway;                                    // 45
        $body .= $ipHost1 . $portHost1 . $ipAltHost1 . $portAltHost1;       // 42 (15+6+15+6)
        $body .= $ipHost2 . $portHost2 . $ipAltHost2 . $portAltHost2;       // 42
        $body .= $listeningPort;                                            //  6
        $body .= CifradoTramas::getHash();                                  // 32
        // Total: 2 + 45 + 42 + 42 + 6 + 32 = 169 chars cuerpo

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
        // ===== Numero de Lote y Secuencial (6 chars cada uno, N, ceros izq) =====
        // Identifican el lote a cerrar. Manual: estos numeros los maneja la
        // cadena (no el Pin Pad), por eso es responsabilidad del cajero
        // mantener su secuencial.
        $batch     = self::padL0((string)($p['batch']     ?? '0'), 6);
        $reference = self::padL0((string)($p['reference'] ?? '0'), 6);

        // ===== Filler 12 chars (campo "Filler" del manual, blancos) =====
        // str_repeat(' ', 12) = 12 espacios consecutivos.
        $filler12 = str_repeat(' ', 12);

        // ===== MID, TID, CID — identificadores del comercio =====
        $mid      = self::padR($p['mid'] ?? '', 15);
        $tid      = self::padR($p['tid'] ?? '', 8);
        $filler23 = str_repeat(' ', 23);  // otro filler entre TID y CID
        $cidTerm  = self::padR($p['cid_terminal'] ?? '', 15);

        // ===== Codigo de Red Activa (1 char) =====
        // Identifica que red procesa este cierre:
        //   "1" = Datafast
        //   "2" = Medianet
        //   "3" = Austro
        // Default "2" porque nuestro caso es Medianet.
        $redActiva = $p['red_activa'] ?? '2';

        // ===== Ensamble =====
        $body  = 'PC';                                              // 2
        $body .= $batch . $reference;                               // 12
        $body .= $filler12;                                         // 12
        $body .= $mid . $tid . $filler23 . $cidTerm;                // 61 (15+8+23+15)
        $body .= $redActiva;                                        //  1
        $body .= CifradoTramas::getHash();                          // 32
        // Total: 2 + 12 + 12 + 61 + 1 + 32 = 120 chars cuerpo

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
    /**
     * RA_TXN_CODES - mapeo de modalidad humana al codigo TXN de la libreria.
     *
     * El bytecode oficial usa:
     *   Corriente y Diferido = "08" (avance cash advance)
     *   Anulacion           = "03"
     *   Reverso             = "04"
     *
     * Es publica como const para que el controller la pueda referenciar
     * en las validaciones de Laravel (ej: `in:` rules).
     */
    public const RA_TXN_CODES = [
        'corriente'  => '08',
        'diferido'   => '08',
        'anulacion'  => '03',
        'reverso'    => '04',
    ];

    /**
     * buildReimpresion() - construye la trama RA (Avance en Efectivo / Cash Advance).
     *
     * Layout extraido del bytecode `model.f.b()` con los nombres de campos
     * mapeados via `viewmodel.f.a()`. El layout es complejo (15 campos):
     *
     *   ["RA":2] + [TXN:2] + [filler:4 sp] + [MOD:2] + [plazo:2 '0'L] + [gracia:2 '0'L]
     *   + [monto:12 '0'L] + [filler:36 sp] + [referencia:6 '0'L]
     *   + [TIME:6 HHmmss] + [DATE:8 yyyyMMdd] + [adquirenteCode:6 '0'L]
     *   + [filler:44 sp] + [serial:15 sp R] + [filler:34 sp]
     *   + [HASH:32]
     *
     * Total: 213 chars cuerpo + 4 prefijo = 217 chars.
     */
    public static function buildReimpresion(string $modalidad, array $p): string
    {
        // Validamos que la modalidad sea una de las 4 soportadas.
        if (!isset(self::RA_TXN_CODES[$modalidad])) {
            throw new \InvalidArgumentException("Modalidad RA invalida: $modalidad");
        }

        // TXN code segun la modalidad (08/03/04 segun caso).
        $txn = self::RA_TXN_CODES[$modalidad];

        // MOD code: cuando es diferido, escoge subtipo (con/sin interes,
        // normal/gracia/mes a mes/especial). Para corriente/anulacion/reverso = "00".
        $modCodes = [
            'normal_con'        => '01',  // Normal Con Interes
            'gracia_con'        => '02',  // Meses de Gracia Con Interes
            'mes_a_mes_con'     => '03',  // Pago Mes a Mes Con Interes
            'especial_con'      => '08',  // Especial Con Interes
            'normal_sin'        => '04',  // Normal Sin Interes
            'gracia_sin'        => '05',  // Meses de Gracia Sin Interes
            'mes_a_mes_sin'     => '06',  // Pago Mes a Mes Sin Interes
            'especial_sin'      => '08',  // Especial Sin Interes (mismo code que con interes)
        ];
        $mod = '00';
        // Solo para diferido leemos el subtipo. Si no esta en el mapa, queda "00".
        if ($modalidad === 'diferido' && !empty($p['diferido_tipo']) && isset($modCodes[$p['diferido_tipo']])) {
            $mod = $modCodes[$p['diferido_tipo']];
        }

        // Para anulacion y reverso, los montos van en cero (no en blanco).
        $isCancel = in_array($modalidad, ['anulacion', 'reverso']);

        // ===== Campos del cuerpo =====

        // Serial (15 chars, AN, blancos a la derecha).
        // Per manual 4.1.6: alfanumerico requerido entre 10 y 15 chars.
        $serial   = self::padR($p['serial'] ?? '', 15);

        // Plazo y Gracia (2 chars cada uno, '0' a la izq).
        // Solo aplican para diferido.
        $plazo  = self::padL0((string)($p['plazo']  ?? '0'), 2);
        $gracia = self::padL0((string)($p['gracia'] ?? '0'), 2);

        // Monto (12 chars centavos). Cero si es anulacion/reverso.
        $totalCents = $isCancel ? 0 : (int) round((float)($p['total'] ?? 0) * 100);
        $monto = self::amountCent($totalCents);

        // Referencia (6 chars '0' L) - secuencial original para anulaciones.
        $referencia = self::padL0((string)($p['referencia'] ?? '0'), 6);

        // Time (6 HHmmss) y Date (8 yyyyMMdd) — actuales por default.
        $time = $p['time'] ?? date('His');
        $date = $p['date'] ?? date('Ymd');

        // Codigo de adquirente (6 chars '0' L). El bytecode usa "0" (Admon Pinpad)
        // por default. Si el cajero quiere especificar otro, puede pasarlo.
        $adquirenteCode = self::padL0((string)($p['adquirente_code'] ?? '0'), 6);

        // ===== Ensamble del cuerpo =====
        // Los fillers son espacios cuyas longitudes vienen del bytecode
        // (no del manual, porque RA no esta en el manual oficial v1.4).
        $body  = 'RA';                           //  2
        $body .= $txn;                           //  2
        $body .= str_repeat(' ', 4);             //  4 (filler tras TXN)
        $body .= $mod;                           //  2
        $body .= $plazo;                         //  2
        $body .= $gracia;                        //  2
        $body .= $monto;                         // 12
        $body .= str_repeat(' ', 36);            // 36 (filler entre montos y refs)
        $body .= $referencia;                    //  6
        $body .= $time;                          //  6
        $body .= $date;                          //  8
        $body .= $adquirenteCode;                //  6
        $body .= str_repeat(' ', 44);            // 44 (filler antes del serial)
        $body .= $serial;                        // 15
        $body .= str_repeat(' ', 34);            // 34 (filler tras serial)
        $body .= CifradoTramas::getHash();       // 32
        // Total: 2+2+4+2+2+2+12+36+6+6+8+6+44+15+34+32 = 213 chars

        return self::withLengthPrefix($body);
    }

    // ============== PARSE RESPONSE ==============

    /**
     * parseResponse() - desestructura la respuesta del Pin Pad en campos utiles.
     *
     * La respuesta tiene la misma estructura general que la peticion:
     *   [longitud:4 hex] + [cuerpo] + [hash:32 hex]
     *
     * El cuerpo varia segun el tipo de operacion y el resultado.
     * Devolvemos un array con todos los campos relevantes para que el frontend
     * pueda mostrarlos sin tener que parsear de nuevo.
     */
    public static function parseResponse(string $resp): array
    {
        // Inicializamos el resultado con todos los campos en null.
        // Asi, si algo falla a la mitad, al menos tenemos una estructura completa.
        $out = [
            'raw'           => $resp,    // bytes crudos como vinieron
            'len_hex'       => null,     // los 4 chars hex de longitud
            'len'           => null,     // longitud parseada (decimal)
            'body'          => null,     // cuerpo SIN prefijo y SIN hash final
            'tipo'          => null,     // 2 chars del tipo de mensaje (ej: "PP", "LT", "CT")
            'cod_pinpad'    => null,     // solo PP: Codigo Respuesta del PINPAD (pos 2-3, ej: "00"=OK, "TO"=timeout)
            'cod_red'       => null,     // solo PP: Codigo Red Adquirente (pos 4-5, ej: "02"=Medianet)
            'mensaje'       => null,     // mensaje humano detectado (ej: "AUTORIZACION OK")
            'hash_recibido' => null,     // los 32 chars hex del final
            'cod_resp'      => null,     // Codigo Respuesta del Autorizador (banco), ej: "00", "62", "@B"
            'cod_resp_desc' => null,     // descripcion humana del codigo
        ];

        // Validacion minima: necesitamos al menos los 4 chars del prefijo.
        if (strlen($resp) < 4) {
            $out['error'] = 'Respuesta demasiado corta';
            return $out;
        }

        // ===== Paso 1: separar prefijo y cuerpo =====
        $out['len_hex'] = substr($resp, 0, 4);          // ej: "0202"
        $out['len']     = hexdec($out['len_hex']);      // hex -> decimal: 514
        // Tomamos exactamente $len chars despues del prefijo.
        $body           = substr($resp, 4, $out['len']);

        // ===== Paso 2: separar el hash 3DES del final =====
        // Si los ultimos 32 chars del cuerpo son hex puro (0-9, A-F), es el hash.
        // Lo separamos para que el resto del cuerpo no se contamine.
        if (strlen($body) >= 32 && preg_match('/^[0-9A-Fa-f]{32}$/', substr($body, -32))) {
            $out['hash_recibido'] = substr($body, -32);
            $body = substr($body, 0, -32);
        }
        $out['body'] = $body;

        // Los primeros 2 chars del cuerpo son el "tipo" (ej: "PP", "LT", "CT").
        // RFC v1.4 p.3/4/8/13/15: Tipo de Mensaje = 2 AN en todos los mensajes.
        $out['tipo'] = trim(substr($body, 0, 2));

        // Para PP, los campos intermedios per RFC v1.4 p.8:
        //   [TIPO:2][COD_PINPAD:2][COD_RED:2][COD_AUTORIZADOR:2][MENSAJE:20][...]
        if (strtoupper($out['tipo']) === 'PP') {
            $out['cod_pinpad'] = substr($body, 2, 2); // generado por el pinpad
            $out['cod_red']    = substr($body, 4, 2); // 01=DF, 02=Medianet, 03=Austro
        }

        // ===== Paso 3: detectar el codigo de respuesta por TEXTO =====
        // El cuerpo tiene un mensaje humano en alguna posicion (ej: "AUTORIZACION OK").
        // En vez de parsear posicion fija (que cambia segun tipo de mensaje),
        // buscamos por patrones regex. Mas robusto.
        $upper = strtoupper($body);

        // Mapeo: codigo => array de regex que deberian matchear ese codigo.
        // Si aparece "AUTORIZACION OK" en el cuerpo, deducimos cod_resp = "00".
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

        // Iteramos todos los patrones. Apenas uno matchea, salimos.
        foreach ($patrones as $cod => $regexes) {
            foreach ($regexes as $rx) {
                if (preg_match($rx, $upper, $m)) {
                    $out['cod_resp'] = $cod;
                    // $m[0] tiene el texto que matcheo (ej: "AUTORIZACION OK")
                    $out['mensaje']  = trim($m[0]);
                    // break 2 = sale de los DOS foreach (el inner Y el outer).
                    break 2;
                }
            }
        }

        // ===== Paso 4: fallback - leer cod_resp por posicion fija =====
        // Layout por RFC v1.4 Mensajeria Caja Pinpad:
        //
        //   PP respuesta (p.8): [TIPO:2][COD_PINPAD:2][COD_RED:2][COD_AUTORIZADOR:2][MENSAJE:20]
        //   LT/CT/PC/CP:        [TIPO:2][COD_RESP:2][resto...]
        //
        // Para PP el codigo que importa (del autorizador/banco) esta en posicion 6-7.
        // Para los demas tipos esta en posicion 2-3 (inmediatamente tras el tipo).
        if ($out['cod_resp'] === null) {
            $offset    = (strtoupper($out['tipo']) === 'PP') ? 6 : 2;
            $candidato = trim(substr($body, $offset, 2));
            if (preg_match('/^[0-9A-Z@]{2}$/i', $candidato)) {
                $out['cod_resp'] = $candidato;
            }
        }

        // ===== Paso 5: agregar la descripcion humana =====
        // Solo si tenemos un codigo (sino queda null).
        $out['cod_resp_desc'] = $out['cod_resp']
            ? self::descripcionCodigoRespuesta($out['cod_resp'])
            : null;

        return $out;
    }

    /**
     * Catalogo COMPLETO de codigos de respuesta.
     * Fuentes:
     *  - Manual Mensajeria Caja Pinpad v1.4 (codigos del Pin Pad: 00-03, 20, TO, ER)
     *  - CatalogoResp.txt de Medianet (todos los demas, del autorizador)
     *
     * Algunos codigos colisionan entre Pin Pad y autorizador (00, 01, 02, 03)
     * - en esos casos se prioriza la interpretacion del Pin Pad / mas comun.
     */
    public static function descripcionCodigoRespuesta(string $cod): string
    {
        static $tabla = [
            // ===== ERRORES INTERNOS DEL PIN PAD / VAP (prefijo "@") =====
            '@1' => 'Error, longitud de trama no coincide',
            '@2' => 'Error, no se puede convertir a ISO8583',
            '@3' => 'Error, autorizador no conectado al VAP',
            '@4' => 'Error, nombre de Autorizador no valido',
            '@5' => 'Error, no hay enlace con Autorizador',
            '@6' => 'Error, no es posible conexion con Autorizador',
            '@7' => 'Error, Autorizador no recibe transaccion',
            '@8' => 'Error, Autorizador no envia respuesta',
            '@9' => 'Error, no existe xp_autoriza.ini',
            '@A' => 'Error, xp_autoriza.ini sin datos',
            '@B' => 'Time Out, autorizador no responde',
            '@C' => 'Tipo de diferido-plazo no permitido',
            '@D' => 'Comercio no autorizado para tipo de diferido',
            '@F' => 'BIN no existe',
            '@K' => 'Error Keyserver servicio no disponible',
            '@L' => 'Error Keyserver, autorizacion no disponible',
            '@R' => 'Mensaje rechazado (Reject Visa)',

            // ===== CODIGOS DEL PIN PAD (manual v1.4) =====
            'TO' => 'Timeout (requiere reverso automatico)',
            'ER' => 'Error conexion Pin Pad',

            // ===== CODIGOS ESTANDAR DEL AUTORIZADOR =====
            '00'  => 'Aprobada',
            '01'  => 'Referir a emisor de tarjeta',
            '02'  => 'Referir a emisor de tarjeta, condicion especial',
            '03'  => 'Comercio o proveedor de servicio invalido',
            '04'  => 'Retener tarjeta',
            '05'  => 'Negada',
            '06'  => 'Error',
            '07'  => 'Retener tarjeta (diferente a perdida o robada)',
            '08'  => 'Aprobada (VIP)',
            '090' => 'Error, no existe suscripcion',
            '10'  => 'Aprobacion parcial',
            '11'  => 'Aprobacion VIP',
            '12'  => 'Transaccion invalida',
            '13'  => 'Monto invalido',
            '14'  => 'Numero de tarjeta invalido',
            '15'  => 'Emisor invalido',
            '17'  => 'Cancelacion del cliente',
            '19'  => 'Reingresar transaccion',
            '20'  => 'Error durante proceso',
            '21'  => 'Ninguna accion tomada (imposible reversar transaccion)',
            '25'  => 'Imposible localizar registro o tarjeta en archivo Boletin',
            '27'  => 'Error de edicion en actualizacion de archivo Boletin',
            '28'  => 'Archivo Boletin no disponible temporalmente',
            '30'  => 'Error de formato',
            '32'  => 'Reservacion parcial',
            '39'  => 'Numero no es de credito (Visa ePay)',
            '40'  => 'Solicitud de servicio no soportada',
            '41'  => 'Retener tarjeta extraviada',
            '43'  => 'Retener tarjeta robada',
            '50'  => 'Fondos insuficientes',
            '51'  => 'Fondos insuficientes',
            '52'  => 'Cuenta no es Corriente o de cheques',
            '53'  => 'Cuenta no es de Ahorros',
            '54'  => 'Tarjeta expirada',
            '55'  => 'PIN invalido',
            '57'  => 'Transaccion no permitida al emisor o tarjetahabiente',
            '58'  => 'Transaccion no permitida al adquirente o terminal',
            '59'  => 'Transaccion sospechosa de fraude',
            '61'  => 'Excede monto limite de la actividad',
            '62'  => 'Tarjeta restringida',
            '63'  => 'Violacion de seguridad',
            '65'  => 'Excede limite de transacciones de la actividad',
            '68'  => 'Respuesta recibida tarde',
            '70'  => 'Transaccion invalida, llamar al emisor',
            '71'  => 'PIN no cambiado',
            '75'  => 'Excedido el numero permitido de intentos del PIN',
            '76'  => 'Imposible localizar mensaje original (no coincide numero referencia)',
            '77'  => 'Mensaje original localizado pero datos de reverso son inconsistentes',
            '78'  => 'Tarjeta usada por primera vez bloqueada (uso Brasil)',
            '79'  => 'Fallo validacion de intercambio de llave',
            '80'  => 'Sistema no disponible',
            '81'  => 'Descifrado del PIN criptografico errado',
            '82'  => 'Resultado negativo en CAM, dCVV, iCVV, CVV',
            '83'  => 'Imposible verificar el PIN',
            '84'  => 'Cambio de PIN',
            '85'  => 'Valido para verificacion de servicios (cuenta, direccion, e-commerce, CVV2)',
            '86'  => 'Imposible verificar el PIN',
            '87'  => 'Aprobado solo monto de compras, no efectivo',
            '88'  => 'PIN criptografico errado en descifrado',
            '89'  => 'PIN no aceptado. Reintente',
            '91'  => 'Emisor no disponible o sistema inoperativo',
            '92'  => 'Destino no puede ser encontrado para envio de transaccion',
            '93'  => 'Transaccion no puede completarse, violacion de ley',
            '94'  => 'Autorizacion duplicada',
            '96'  => 'Mal funcionamiento del sistema o campos con error',
            '97'  => 'No existe servicio IP definido en catalogo',
            '98'  => 'Error envio transaccion a servicio IP',

            // ===== CODIGOS ESPECIFICOS DE TARJETAS / EMISORES =====
            'B1'  => 'Recargo no permitido para tarjetas Visa',
            'e1'  => 'Fondos insuficientes en extracupo',

            // ===== STAND-IN PROCESSING (MasterCard / Emisor remoto) =====
            'M0'  => 'Opcion de Stand-In seleccionada por Emisor',
            'M1'  => 'Sistema del Emisor solicita estar fuera de servicio',
            'M2'  => 'Sistema del Emisor responde tarde (time out)',
            'M3'  => 'Sistema del Emisor no disponible',
            'M4'  => 'Transaccion procesada via Limite-1',
            'M5'  => 'Transaccion procesada via X-Code',
            'M6'  => 'Transaccion procesada via Limite-1 en el MIP',
            'M7'  => 'Error de procesamiento del PIN',
            'M8'  => 'Ruta alternativa del Emisor por error de MIP',
            'M9'  => 'Emisor responde datos con error',
            'MA'  => 'Sistema Host del Emisor con error',
            'MB'  => 'Error de red no enviado',
            'MC'  => 'Emisor no puede responder',
            'MD'  => 'Ruta alternativa del Emisor: direct down option',
            'ME'  => 'Transaccion procesada via MIP decision de Servicio On-Behalf',
            'MF'  => 'Servicio de consulta de cuenta',
            'MG'  => 'Deshabilitada la conversion PayPass o numero de cuenta virtual',
            'MH'  => 'Aprobado por APS (Acquirer Processing System)',
            'MI'  => 'Transmision de mensaje administrativo',
            'MJ'  => 'Comercio invalido',
            'MK'  => 'Incontrol processing advice to issuer',

            // ===== STIP (Stand-In Processing) FORZADO =====
            'N0'  => 'STIP forzado',
            'N3'  => 'Servicio de efectivo no disponible (no ATM)',
            'N4'  => 'Solicitud de efectivo supera limite de emisor (no ATM)',
            'N7'  => 'Negada por CVV2 invalido',

            // ===== PVID (PIN Verification ID) =====
            'P0'  => 'Aprobada; codigo PVID no encontrado, invalido o ha expirado',
            'P1'  => 'Declinada; codigo PVID no encontrado, invalido o ha expirado',
            'P2'  => 'Informacion invalida del facturador',
            'P5'  => 'Declinado el Cambio o Desbloqueo de PIN',
            'P6'  => 'PIN inseguro',

            // ===== AUTENTICACION =====
            'Q1'  => 'Autenticacion de la tarjeta fallida',

            // ===== ORDENES DE REVOCACION =====
            'R0'  => 'Orden de pago suspendida',
            'R1'  => 'Orden de revocacion de autorizacion',
            'R3'  => 'Orden de revocacion de todas las autorizaciones',

            // ===== STIP VISA =====
            'V1'  => 'Resp. STIP Visa, Sistema del Emisor no responde (time out)',
            'V2'  => 'Resp. STIP Visa, monto bajo limite autorizado por Emisor (PCAS)',
            'V3'  => 'Resp. STIP Visa, Emisor en modo SI (Supress Inquiries)',
            'V4'  => 'Resp. STIP Visa, Emisor no habilitado o CVV, iCVV, PVV invalido',
            'V5'  => 'Respuesta provista por el Emisor',
            'V6'  => 'Solicita emisor que responda STIP (Stand-In Processing)',
            'V7'  => 'Reverso de advice por Visa, transaccion duplicada',
            'V8'  => 'Reverso de advice por Visa, autorizacion duplicada',
            'V9'  => 'Resp. STIP Visa IARS (International Automated Referral Service)',

            // ===== REENVIO Y OTROS =====
            'XA'  => 'Reenviar al emisor (default "00")',
            'XD'  => 'Reenviar al emisor (default "05")',
            'Z3'  => 'Declinada, no disponible en linea',
        ];
        return $tabla[$cod] ?? "Codigo desconocido ($cod)";
    }
}
