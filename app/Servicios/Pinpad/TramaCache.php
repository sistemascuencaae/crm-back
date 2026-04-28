<?php

namespace App\Servicios\Pinpad;

use Illuminate\Support\Facades\Cache;

/**
 * Replica el cache interno de la libreria oficial:
 *   - Tras una transaccion exitosa (PP/RA corriente o diferido),
 *     se guarda una "trama de reverso pre-armada" sobreescribiendo
 *     char[7] (segundo char del TXN) por '4'.
 *   - Cuando se solicita un reverso, NO se arma una trama nueva:
 *     se envia la cacheada (mismo hash, time/date, montos, etc.)
 *     para que el switch pueda matchearla con la transaccion original.
 *
 * TTL: el manual oficial (4.1.4) dice que el reverso siempre apunta a
 * "la ultima transaccion" sin TTL explicito - la libreria JavaFX lo
 * mantiene en memoria mientras la ventana este abierta.
 * Default 480 min (8h = un turno tipico). Configurable via
 * PINPAD_REVERSO_TTL_MINUTES en .env.
 */
final class TramaCache
{
    private const KEY_PREFIX = 'pinpad:reverso:';

    /**
     * Despues de una transaccion exitosa, guarda la version "reverso".
     * Replica el setCharAt(7, '4') del bytecode oficial.
     *
     * @param string $tid           Terminal ID (clave para multi-caja)
     * @param string $tramaOriginal Trama enviada (incluye prefijo de longitud)
     */
    public static function storeReverso(string $tid, string $tramaOriginal): void
    {
        if (strlen($tramaOriginal) < 8) return;

        // char[7] = segundo char de TXN. "01"->"04", "02"->"04", "08"->"04"
        $tramaReverso = substr($tramaOriginal, 0, 7) . '4' . substr($tramaOriginal, 8);

        Cache::put(self::key($tid), $tramaReverso, now()->addMinutes(self::ttlMinutes()));
    }

    /** TTL en minutos, configurable via .env (default 480 = 8h). */
    private static function ttlMinutes(): int
    {
        return max(1, (int) config('pinpad.reverso_ttl_minutes', 480));
    }

    /** Devuelve la trama de reverso pre-armada o null si no hay. */
    public static function getReverso(string $tid): ?string
    {
        return Cache::get(self::key($tid));
    }

    public static function hasReverso(string $tid): bool
    {
        return Cache::has(self::key($tid));
    }

    /** Borra la cache (tras un reverso exitoso o un cierre de turno). */
    public static function clearReverso(string $tid): void
    {
        Cache::forget(self::key($tid));
    }

    private static function key(string $tid): string
    {
        return self::KEY_PREFIX . trim($tid);
    }
}
