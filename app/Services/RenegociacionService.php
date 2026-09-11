<?php

namespace App\Services;

/**
 * Helper del flujo de renegociación con solicitud + aprobación.
 *
 * La LÓGICA DE NEGOCIO (validar cuota, derivar tipo, propagación +1 mes,
 * snapshot _2, reversión con regla de seguridad y permiso) vive en funciones
 * de PostgreSQL (ver sql/renegociacion_funciones.sql, prefijo crm.fn_reneg_*).
 * El controller las invoca como pasarela fina.
 *
 * Lo único que queda en PHP es la etiqueta del usuario autenticado, que
 * depende del contexto de auth y se pasa como parámetro a las funciones.
 */
class RenegociacionService
{
    /** Etiqueta "usu_alias - surname name" para created_by / updated_by. */
    public static function etiquetaUsuario($user): ?string
    {
        if (!$user) {
            return null;
        }
        return trim(($user->usu_alias ?? '') . ' - ' . ($user->surname ?? '') . ' ' . ($user->name ?? ''));
    }
}
