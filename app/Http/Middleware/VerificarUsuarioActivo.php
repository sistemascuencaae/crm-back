<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

// JGSJ MIDDLEWARE USUARIO ACTIVO usuario.activo
class VerificarUsuarioActivo
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

// metodo 
// public function handle(Request $request, Closure $next)
//     {
//         try {
//             // Obtener el usuario desde el token JWT
//             $user = JWTAuth::parseToken()->authenticate();

//             // Verificar si el usuario está desactivado
//             if (!$user || !$user->estado) {
//                 return response()->json([
//                     'message' => 'El usuario está inactivo. Acceso denegado.',
//                 ], 401);
//             }

//             return $next($request);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'message' => 'Error de autenticación',
//                 'error' => $e->getMessage()
//             ], 401);
//         }
//     }


    public function handle(Request $request, Closure $next)
    {
        try {
            // Autenticar el usuario desde el token JWT
            $user = JWTAuth::parseToken()->authenticate();

            // Validar si el usuario está activo
            if (!$user || !$user->estado) {
                return response()->json([
                    'message' => 'El usuario está inactivo. Acceso denegado.',
                ], 401);
            }

            // Obtener horario permitido desde la tabla
            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'HORARIO')
                ->first();

            if ($parametro && $parametro->valor) {
                // Separar hora_inicio y hora_fin
                [$horaInicio, $horaFin] = explode('-', $parametro->valor);

                // Obtener la hora actual del servidor
                $horaActual = now()->format('H:i');

                // Validar si está fuera del rango permitido
                if ($horaActual < $horaInicio || $horaActual > $horaFin) {
                    return response()->json([
                        'message' => "Acceso denegado. Horario permitido: $horaInicio a $horaFin",
                    ], 401);
                }
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error de autenticación',
                'error' => $e->getMessage()
            ], 401);
        }
    }

}
