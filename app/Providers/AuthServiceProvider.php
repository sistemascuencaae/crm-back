<?php

namespace App\Providers;

use App\Auth\ClientesWebProveedor;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Sesión de los clientes de la tienda: el cliente sale de la función de la base, no de la tabla
        Auth::provider('clientes_web_fn', fn () => new ClientesWebProveedor());
    }
}
