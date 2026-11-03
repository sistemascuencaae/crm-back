<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Http\Middleware\Authenticate;

/**
 * jwt.auth del CRM: el de tymon, y además rechaza los tokens de los clientes de la tienda.
 * El de tymon solo busca el 'sub' del token en crm.users (no revisa el modelo con el que se emitió): sin esto, el token
 * del cliente 5 entraría al CRM como el usuario 5. Se rechaza con la misma excepción que tymon usa para un token
 * inválido, así el CRM recibe la misma respuesta de siempre. Kernel apunta el alias jwt.auth a esta clase.
 */
class AutenticarJwtCrm extends Authenticate
{
    public function handle($request, Closure $next)
    {
        $this->authenticate($request);

        if ($this->auth->getPayload()->get('tipo') === 'cliente') {
            throw new UnauthorizedHttpException('jwt-auth', 'User not found');
        }

        return $next($request);
    }
}
