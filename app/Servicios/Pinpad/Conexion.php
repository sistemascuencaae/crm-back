<?php

namespace App\Servicios\Pinpad;

use RuntimeException;

/**
 * Cliente TCP para Pin Pad Medianet.
 * Equivalente a cajapinpad.Conexion del JAR oficial:
 *   - sendData(ip, port, byte[], timeout)
 *   - getDataRecived()
 *
 * Aqui se unifica en un solo metodo sendRecv() que abre socket,
 * envia los bytes y lee hasta timeout.
 */
final class Conexion
{
    /**
     * @param string $ip          IP del Pin Pad (ej: 192.168.1.242)
     * @param int    $port        Puerto TCP (ej: 7380)
     * @param string $frame       Bytes de la trama tal cual saldran al socket
     * @param int    $timeoutMs   Timeout en milisegundos
     * @return string             Respuesta cruda del Pin Pad
     */
    public static function sendRecv(string $ip, int $port, string $frame, int $timeoutMs = 30000): string
    {
        $errno  = 0;
        $errstr = '';
        $connectTimeout = max(1, (int) ceil($timeoutMs / 1000));

        $sock = @fsockopen($ip, $port, $errno, $errstr, $connectTimeout);
        if ($sock === false) {
            throw new RuntimeException("Pinpad TCP connect a $ip:$port fallo: $errstr ($errno)");
        }

        // Timeout de lectura/escritura
        $sec  = intdiv($timeoutMs, 1000);
        $usec = ($timeoutMs % 1000) * 1000;
        stream_set_timeout($sock, $sec, $usec);

        $written = fwrite($sock, $frame);
        if ($written === false || $written < strlen($frame)) {
            fclose($sock);
            throw new RuntimeException("Pinpad: escritura incompleta ($written de " . strlen($frame) . " bytes)");
        }

        // El JAR original lee 1024 bytes en un solo read; aqui acumulamos hasta cerrar
        // el socket o cumplir el timeout (mas robusto, no rompe la compatibilidad).
        $resp = '';
        $deadline = microtime(true) + ($timeoutMs / 1000);
        while (!feof($sock) && microtime(true) < $deadline) {
            $chunk = fread($sock, 1024);
            if ($chunk === false || $chunk === '') {
                $info = stream_get_meta_data($sock);
                if (!empty($info['timed_out'])) break;
                if (!empty($info['eof']))      break;
                usleep(50000);
                continue;
            }
            $resp .= $chunk;
            // Si la respuesta empieza con prefijo de longitud (4 hex) y ya completamos,
            // salir antes de timeout.
            if (strlen($resp) >= 4) {
                $declared = @hexdec(substr($resp, 0, 4));
                if ($declared > 0 && strlen($resp) >= $declared + 4) break;
            }
        }
        fclose($sock);

        if ($resp === '') {
            throw new RuntimeException("Pinpad: no respondio dentro de $timeoutMs ms");
        }
        return $resp;
    }

    /**
     * Comprueba conectividad TCP sin enviar nada (pre-flight check).
     */
    public static function probe(string $ip, int $port, int $timeoutMs = 3000): bool
    {
        $errno = 0; $errstr = '';
        $sock = @fsockopen($ip, $port, $errno, $errstr, max(1, $timeoutMs / 1000));
        if ($sock === false) return false;
        fclose($sock);
        return true;
    }
}
