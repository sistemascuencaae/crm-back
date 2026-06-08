<?php

namespace App\Servicios\Pinpad;

use RuntimeException;

/**
 * Conexion — cliente TCP simple para hablarle al Pin Pad.
 *
 * Es el equivalente PHP de `cajapinpad.Conexion` del JAR oficial:
 *   - Abre socket TCP a la IP/puerto del Pin Pad
 *   - Manda los bytes de la trama
 *   - Lee la respuesta hasta cumplir el timeout o cerrar
 *   - Cierra el socket
 *
 * Por conexion se hace UNA transaccion. No hay "keep-alive" ni multiplexado.
 */
final class Conexion
{
    /**
     * sendRecv() - envia una trama y devuelve la respuesta.
     *
     * @param string $ip          IP del Pin Pad (ej: 192.168.1.242)
     * @param int    $port        Puerto TCP (ej: 6500)
     * @param string $frame       La trama lista para enviar (con prefijo + cuerpo + hash)
     * @param int    $timeoutMs   Tiempo maximo para esperar respuesta, en milisegundos
     * @return string             Bytes que devolvio el Pin Pad
     * @throws RuntimeException   si falla la conexion o no hay respuesta
     */
    public static function sendRecv(string $ip, int $port, string $frame, int $timeoutMs = 30000): string
    {
        // Variables que fsockopen() llena por referencia con info del error
        $errno  = 0;
        $errstr = '';

        // fsockopen toma timeout en SEGUNDOS (no en ms). Convertimos.
        // max(1, ...) evita pasar 0 segundos que significaria "no esperes".
        $connectTimeout = max(1, (int) ceil($timeoutMs / 1000));

        // Abre la conexion TCP. El @ silencia warnings de PHP en caso de fallo
        // (igual chequeamos $sock === false abajo).
        $sock = @fsockopen($ip, $port, $errno, $errstr, $connectTimeout);

        // Si no se pudo conectar, lanzamos excepcion con detalle del error.
        // Casos tipicos: Pin Pad apagado, IP equivocada, firewall, etc.
        if ($sock === false) {
            throw new RuntimeException("Pinpad TCP connect a $ip:$port fallo: $errstr ($errno)");
        }

        // Configuramos timeout de lectura/escritura en el socket ya abierto.
        // PHP toma 2 args: segundos enteros + microsegundos para precision.
        // Ej: 30000ms = 30 segundos + 0 microsegundos.
        $sec  = intdiv($timeoutMs, 1000);              // 30
        $usec = ($timeoutMs % 1000) * 1000;            // 0 (microsegundos)
        stream_set_timeout($sock, $sec, $usec);

        // ENVIO: fwrite manda los bytes del frame al socket.
        // Devuelve el numero de bytes escritos, o false si fallo.
        $written = fwrite($sock, $frame);
        if ($written === false || $written < strlen($frame)) {
            fclose($sock);
            throw new RuntimeException("Pinpad: escritura incompleta ($written de " . strlen($frame) . " bytes)");
        }

        // RECEPCION: el Pin Pad puede mandar la respuesta en varios paquetes
        // TCP (especialmente si es larga). Leemos en loop hasta que:
        //   - se cumple el timeout, o
        //   - se cierra la conexion (eof), o
        //   - ya tenemos la trama completa (segun su prefijo de longitud).
        $resp = '';
        $deadline = microtime(true) + ($timeoutMs / 1000);

        while (!feof($sock) && microtime(true) < $deadline) {
            // Leer hasta 1024 bytes en este intento.
            $chunk = fread($sock, 1024);

            if ($chunk === false || $chunk === '') {
                // No vino nada en este read. Verificamos si fue por timeout o eof.
                $info = stream_get_meta_data($sock);
                if (!empty($info['timed_out'])) break;
                if (!empty($info['eof']))      break;
                // Si no fue ninguno, esperamos 50ms y reintentamos
                // (evita un busy-loop comiendo CPU).
                usleep(50000);
                continue;
            }

            // Acumulamos lo recibido
            $resp .= $chunk;

            // Optimizacion: si ya leimos suficiente segun el prefijo de
            // longitud (4 chars hex al inicio), podemos salir antes de timeout.
            if (strlen($resp) >= 4) {
                $declared = @hexdec(substr($resp, 0, 4));
                if ($declared > 0 && strlen($resp) >= $declared + 4) break;
            }
        }
        fclose($sock);

        // Si no recibimos NADA, probablemente el Pin Pad no esta respondiendo.
        // Esto NO siempre es un error fatal — podria ser un timeout que
        // requiere enviar un reverso despues.
        if ($resp === '') {
            throw new RuntimeException("Pinpad: no respondio dentro de $timeoutMs ms");
        }
        return $resp;
    }

    /**
     * probe() - chequeo rapido de "esta vivo el Pin Pad?".
     *
     * Solo abre y cierra el socket, sin enviar nada. Util para diagnostico
     * antes de intentar una transaccion completa.
     *
     * @return bool  true si pudo conectar, false si no.
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
