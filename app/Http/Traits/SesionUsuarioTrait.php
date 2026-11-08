<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// 07-nov: el "user" de la sesión del CRM, armado EXACTAMENTE como lo armaba JWTController::respondWithToken: las mismas 14 llaves,
// en el mismo orden y con los mismos tipos. Lo usan el login (respondWithToken), GET /api/access/sesion y el middleware
// CabeceraSesionUsuario (cabecera X-Sesion-Usuario), así que los tres responden lo mismo.
// alm_id es el CÓDIGO de crm.agencia como texto (null si la agencia del usuario no existe) y alm_nombre su nombre ('' si no existe).
trait SesionUsuarioTrait
{
    // Minutos que vive la caché del nombre de las agencias (código => nombre) que usa la cabecera X-Sesion-Usuario.
    protected static $minutosCacheAgenciasSesion = 5;

    // Agencia del usuario con la MISMA consulta que tenía respondWithToken (una consulta a la base).
    protected function agenciaSesionUsuario($usuario): ?object
    {
        $alm = DB::selectOne('SELECT a.nombre AS alm_nombre, a.codigo AS alm_id
                                        FROM crm.users u
                                    INNER JOIN crm.agencia a ON a.codigo = CAST(u.alm_id AS VARCHAR)
                                    WHERE u.id = ?', [$usuario->id]);

        return $alm ?: null;
    }

    // La misma agencia, pero desde una caché de unos minutos con todas las agencias (código => nombre): no suma consultas por petición.
    // Compara igual que la consulta: crm.agencia.codigo = CAST(crm.users.alm_id AS VARCHAR).
    protected function agenciaSesionUsuarioDesdeCache($usuario): ?object
    {
        if ($usuario->alm_id === null) {
            return null;
        }

        $agencias = Cache::remember('sesion_usuario_agencias', now()->addMinutes(static::$minutosCacheAgenciasSesion), function () {
            $mapa = [];
            foreach (DB::select('SELECT codigo, nombre FROM crm.agencia ORDER BY id') as $agencia) {
                $codigo = (string) $agencia->codigo;
                if (!array_key_exists($codigo, $mapa)) {
                    $mapa[$codigo] = $agencia->nombre;
                }
            }
            return $mapa;
        });

        $codigo = (string) $usuario->alm_id;
        if (!is_array($agencias) || !array_key_exists($codigo, $agencias)) {
            return null;
        }

        return (object) ['alm_nombre' => $agencias[$codigo], 'alm_id' => $codigo];
    }

    // El arreglo "user" de la sesión. $enLinea va aparte porque el login lo devuelve recién puesto en true.
    // $alm es lo que devuelve agenciaSesionUsuario() o agenciaSesionUsuarioDesdeCache() (null = sin agencia).
    protected function armarUsuarioSesion($usuario, $enLinea, ?object $alm): array
    {
        return [
            "id" => $usuario->id,
            "en_linea" => $enLinea,
            "full_name" => $usuario->usu_alias . ' - ' . $usuario->name . ' ' . $usuario->surname,
            "name" => $usuario->name,
            "surname" => $usuario->surname,
            "email" => $usuario->email,
            "usu_tipo_analista" => $usuario->usu_tipo_analista,
            "usu_tipo" => $usuario->usu_tipo,
            "usu_alias" => $usuario->usu_alias,
            "dep_id" => $usuario->dep_id,
            "profile_id" => $usuario->profile_id,
            "bod_id" => $usuario->bod_id,
            "alm_nombre" => $alm ? $alm->alm_nombre : '',
            "alm_id" => $alm ? $alm->alm_id : null,
        ];
    }
}
