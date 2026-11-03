<?php

namespace App\Http\Middleware;

use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * cliente.sesion: rutas de la cuenta de un cliente de la tienda. Pasa solo con un token del guard 'cliente'
 * (marca tipo = cliente), de una cuenta activa, emitido después de su "sesiones_desde" (cambiar o restablecer la
 * contraseña y la desactivación desde el CRM lo mueven). Si no, responde SESION_VENCIDA (code 401 en
 * el cuerpo, HTTP 200). Deja el perfil en el request (cliente_perfil) para que el controlador no lo vuelva a consultar.
 * Un token emitido hasta 2 s antes de mover sesiones_desde todavía pasa (margen por la hora distinta de los servidores).
 */
class VerificarSesionCliente
{
    public function handle(Request $request, Closure $next)
    {
        $guard   = auth('cliente');
        $cliente = $guard->user();   // null: sin token, vencido, firma mala, en la lista negra o de otro modelo

        if ($cliente === null || $guard->payload()->get('tipo') !== 'cliente') {
            return $this->vencida();
        }

        // Sin filas: desactivada. 'ahora_base': la hora de la base en esta misma consulta
        $perfil = DB::selectOne('SELECT p.*, extract(epoch from now()) AS ahora_base FROM ecommerce.fn_cliente_web_perfil(?) p', [$cliente->clw_id]);

        if ($perfil === null) {
            return $this->vencida();
        }

        // sesiones_desde es hora de la base y el iat del token es hora de este servidor, y no siempre coinciden: el iat se
        // pasa a la hora de la base. Margen de 2 s por el redondeo del iat (segundos enteros) y la demora de la consulta.
        $emitido = $guard->payload()->get('iat') + ($perfil->ahora_base - microtime(true));
        if ($emitido < Carbon::parse($perfil->sesiones_desde)->timestamp - 2) {
            return $this->vencida();
        }

        unset($perfil->ahora_base);
        $request->attributes->set('cliente_perfil', $perfil);

        return $next($request);
    }

    private function vencida()
    {
        return ErroresPaginaWeb::errorCliente('SESION_VENCIDA', null, null, 401);
    }
}
