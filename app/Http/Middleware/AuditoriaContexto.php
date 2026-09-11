<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// JGSJ MIDDLEWARE CONTEXTO DE AUDITORÍA FORENSE (app.*)
// Setea en la conexión PostgreSQL del request el usuario del CRM, su IP, el user agent
// y un request_id vía set_config('app.*'), para que los triggers de auditoría
// (auditoria.fn_auditar_cambios) sepan QUIÉN hizo el cambio cuando viene del CRM.
// Si la operación llega DIRECTO a la BD (psql/DBeaver/otro sistema), estos settings no
// existen y la auditoría cae al usuario de base de datos con marca DIRECT_DB_OPERATION.
class AuditoriaContexto
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // El guard JWT resuelve el usuario desde el header Authorization por sí solo;
            // en rutas públicas (login) devuelve null y simplemente no se setea contexto.
            $u = auth('api')->user();

            if ($u) {
                // set_config(..., false) = nivel SESIÓN: vale para toda la conexión de este
                // request (dentro y fuera de transacciones); la conexión muere con el request.
                DB::select(
                    'SELECT set_config(?, ?, false), set_config(?, ?, false), set_config(?, ?, false),
                            set_config(?, ?, false), set_config(?, ?, false), set_config(?, ?, false)',
                    [
                        'app.usuario_id', (string) $u->id,
                        'app.usuario_login', (string) ($u->usu_alias ?? ''),
                        'app.usuario_nombre', trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')),
                        'app.ip_address', (string) $request->ip(),
                        'app.user_agent', (string) $request->userAgent(),
                        'app.request_id', (string) Str::uuid(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // El contexto de auditoría es best-effort: nunca debe tumbar el request.
        }

        return $next($request);
    }
}
