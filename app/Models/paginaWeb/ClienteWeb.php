<?php

namespace App\Models\paginaWeb;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Cliente de la tienda web (ecommerce.clientes_web), aparte de los usuarios del CRM (crm.users).
 * Solo se usa para emitir y leer su JWT (guard 'cliente'): nunca para guardar. Todo cambio va por las funciones
 * ecommerce.fn_cliente_web_* (ClienteWebService).
 */
class ClienteWeb extends Authenticatable implements JWTSubject
{
    protected $table      = 'ecommerce.clientes_web';
    protected $primaryKey = 'clw_id';
    public $timestamps    = false;

    protected $hidden = ['clw_contrasena'];

    public function getAuthPassword()
    {
        return $this->clw_contrasena;
    }

    public function getJWTIdentifier()
    {
        return $this->clw_id;
    }

    // Marca del token: VerificarSesionCliente la exige y AutenticarJwtCrm rechaza estos tokens en el CRM
    public function getJWTCustomClaims()
    {
        return ['tipo' => 'cliente'];
    }
}
