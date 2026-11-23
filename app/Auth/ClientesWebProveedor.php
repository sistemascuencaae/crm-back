<?php

namespace App\Auth;

use App\Models\paginaWeb\ClienteWeb;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

/**
 * Lector de la sesión de los clientes de la tienda (guard 'cliente', config/auth.php): con cada token arma el cliente
 * desde la función de la base (ecommerce.fn_cliente_web_perfil), sin leer la tabla. Una cuenta desactivada no sale, así
 * que su token deja de servir.
 * Los clientes no ingresan con este lector: el ingreso lo hace ClientesTiendaController (compara la contraseña y emite el
 * JWT), por eso no busca por credenciales ni usa "recordarme".
 */
class ClientesWebProveedor implements UserProvider
{
    // El cliente del token: {clw_id, clw_correo, clw_nombres, …} o null si no existe o está desactivado
    public function retrieveById($identifier)
    {
        $perfil = DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_perfil(?)', [(int) $identifier]);

        return $perfil ? self::cliente((array) $perfil) : null;
    }

    // Un cliente para emitir su JWT a partir de su perfil (no lee nada más: el JWT solo necesita su id)
    public static function cliente(array $perfil): ClienteWeb
    {
        $cliente = (new ClienteWeb())->forceFill($perfil);
        $cliente->exists = true;

        return $cliente;
    }

    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
    }

    public function retrieveByCredentials(array $credentials)
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return false;
    }
}
