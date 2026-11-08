<?php

namespace App\Http\Middleware;

use App\Http\Traits\SesionUsuarioTrait;
use Closure;
use Illuminate\Http\Request;

// 07-nov: cabecera X-Sesion-Usuario. Después de responder, si la petición trajo un token válido, agrega el usuario ACTUAL de la base
// (el mismo "user" del login, en JSON y en base64 UTF-8, porque hay nombres con Ñ). El front lo aplica en memoria en cada respuesta,
// así un cambio de agencia, perfil o tipo se ve en el siguiente clic, sin cerrar sesión ni recargar.
// - No suma consultas: usa el usuario que el guard ya cargó en esta petición (throttle:api y AuditoriaContexto lo cargan al entrar)
//   y el nombre de la agencia sale de una caché de unos minutos (SesionUsuarioTrait::agenciaSesionUsuarioDesdeCache).
// - Sin token (login, formulario externo, página web) no agrega nada. Tampoco en un 401: ahí el front cierra la sesión.
// - Cualquier error se traga: la cabecera nunca rompe la respuesta.
// Para que el navegador la deje leer desde otro origen va en exposed_headers de config/cors.php.
class CabeceraSesionUsuario
{
    use SesionUsuarioTrait;

    public const CABECERA = 'X-Sesion-Usuario';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            // La petición debe traer su propio token (el front manda "bearer <token>"; el login no manda ninguno).
            if (!preg_match('/bearer\s+\S+/i', (string) $request->headers->get('Authorization', ''))) {
                return $response;
            }

            if (!is_object($response) || !isset($response->headers) || (method_exists($response, 'getStatusCode') && $response->getStatusCode() === 401)) {
                return $response;
            }

            // Solo si el guard YA tiene al usuario: así no dispara otra consulta. Con un token vencido o inválido no lo tiene.
            $guard = auth('api');
            if (!method_exists($guard, 'hasUser') || !$guard->hasUser()) {
                return $response;
            }

            $usuario = $guard->user();
            if (!$usuario) {
                return $response;
            }

            $user = $this->armarUsuarioSesion($usuario, $usuario->en_linea, $this->agenciaSesionUsuarioDesdeCache($usuario));
            $json = json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if ($json !== false) {
                $response->headers->set(self::CABECERA, base64_encode($json));
            }
        } catch (\Throwable $e) {
            // La cabecera es un extra: si algo falla, la respuesta sale igual, sin ella.
        }

        return $response;
    }
}
