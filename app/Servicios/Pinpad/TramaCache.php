<?php

namespace App\Servicios\Pinpad;

// Cache es la facade que abstrae el driver de cache configurado en .env
// (file, redis, memcached, etc). Por defecto en Laravel usa "file".
use Illuminate\Support\Facades\Cache;

/**
 * TramaCache — replica el cache interno de la libreria oficial.
 *
 * QUE PROBLEMA RESUELVE:
 * Cuando el cajero hace un cobro y el switch Medianet se cae a la mitad
 * (timeout), la transaccion puede haber quedado autorizada en el banco
 * pero sin que el cajero lo sepa. La solucion oficial es enviar un
 * "reverso" que tenga EXACTAMENTE los mismos datos (montos, hora, fecha,
 * hash) que la transaccion original, solo cambiando el codigo TXN a "04".
 *
 * Si construyeramos el reverso desde cero, el TIME/DATE serian distintos
 * y el switch no encontraria la transaccion para reversar.
 *
 * Por eso la libreria oficial guarda en memoria una "version pre-armada
 * del reverso" tras cada transaccion exitosa: la misma trama pero con
 * char[7] sobreescrito a '4'. Cuando se pide reverso, se manda esa.
 */
final class TramaCache
{
    // Prefijo de la clave en el cache. Permite multi-terminal: cada TID
    // tiene su propia entrada (clave = "pinpad:reverso:AEP00101", etc.).
    private const KEY_PREFIX = 'pinpad:reverso:';

    /**
     * storeReverso() - guarda la version reverso de una trama recien enviada.
     *
     * La libreria oficial hace `stringBuilder.setCharAt(7, '4')` que es
     * equivalente a sobreescribir el byte 7 (8vo byte, indice 0-based).
     *
     * Posiciones en la trama:
     *   bytes 0-3 = prefijo de longitud (ej: "00d4")
     *   bytes 4-5 = idMsj (ej: "PP")
     *   bytes 6-7 = TXN (ej: "01" = corriente)
     *
     * Al cambiar byte[7] a '4', "01" se convierte en "04" = REVERSO.
     */
    public static function storeReverso(string $tid, string $tramaOriginal): void
    {
        // Si la trama es muy corta, no podemos hacer el switch — algo falla.
        if (strlen($tramaOriginal) < 8) return;

        // substr() para tomar las partes y reconstruir con '4' en posicion 7:
        //   substr($s, 0, 7)  => bytes 0..6 (los 7 primeros chars)
        //   . '4' .           => agrega el char nuevo en posicion 7
        //   substr($s, 8)     => bytes 8 hasta el final (saltando el byte 7)
        $tramaReverso = substr($tramaOriginal, 0, 7) . '4' . substr($tramaOriginal, 8);

        // Guarda en cache con TTL en minutos. Pasado ese tiempo, se borra solo.
        // now() es un helper de Carbon (Laravel) que devuelve la fecha actual.
        // addMinutes(N) agrega N minutos a esa fecha.
        Cache::put(self::key($tid), $tramaReverso, now()->addMinutes(self::ttlMinutes()));
    }

    /**
     * getReverso() - recupera la trama de reverso cacheada (o null si no hay).
     */
    public static function getReverso(string $tid): ?string
    {
        return Cache::get(self::key($tid));
    }

    /**
     * hasReverso() - chequea SOLO si existe una entrada cacheada,
     * sin traer su contenido. Util para el endpoint /reverso-disponible.
     */
    public static function hasReverso(string $tid): bool
    {
        return Cache::has(self::key($tid));
    }

    /**
     * clearReverso() - borra el cache. Se llama:
     *   - tras un reverso exitoso (no hay nada que reversar dos veces)
     *   - opcionalmente al hacer cierre de turno
     */
    public static function clearReverso(string $tid): void
    {
        Cache::forget(self::key($tid));
    }

    /**
     * TTL en minutos, configurable via .env (default 480 = 8 horas).
     * 8 horas cubre un turno tipico de caja sin que se vuelva stale.
     */
    private static function ttlMinutes(): int
    {
        // config() lee de config/pinpad.php que a su vez lee de .env
        // max(1, ...) evita TTL de 0 o negativo si alguien pone basura en .env
        return max(1, (int) config('pinpad.reverso_ttl_minutes', 480));
    }

    /**
     * Construye la clave del cache: "pinpad:reverso:{TID}".
     * trim() por si el TID viene con espacios accidentales.
     */
    private static function key(string $tid): string
    {
        return self::KEY_PREFIX . trim($tid);
    }
}
