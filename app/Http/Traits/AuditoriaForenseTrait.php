<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

// El bloque 'auditoria' que reciben las funciones SQL y guardan en auditoria.logs_cambios: quién hizo el cambio
// (usuario del token), desde dónde (IP y navegador) y un request_id para agrupar lo de una misma petición.
trait AuditoriaForenseTrait
{
    protected function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}
