<?php

namespace App\Http\Resources\fileManager;

/**
 * Utilidades de query para el File Manager.
 */
class FmQueryHelper
{
    /**
     * Escapa los wildcards de LIKE/ILIKE para que `%` y `_` del input del
     * usuario se traten como literales y no como comodines.
     *
     * El backslash se escapa primero (sino entraría en bucle con los
     * reemplazos posteriores). PostgreSQL usa `\` como carácter de escape
     * por defecto para LIKE patterns, así que no hace falta `ESCAPE`.
     *
     * Uso: `'%' . FmQueryHelper::escaparLike($q) . '%'`
     */
    public static function escaparLike(string $q): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $q,
        );
    }
}
