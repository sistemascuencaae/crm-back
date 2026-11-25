<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * El proxy 192.168.254.6 está delante del servidor y manda la IP real del visitante en X-Forwarded-For (y el
     * https en X-Forwarded-Proto). Sin confiar en él, Laravel veía a todos los visitantes con la IP del proxy: los
     * límites de peticiones (throttle, que van por IP) eran uno solo para toda la tienda.
     *
     * @var array|string|null
     */
    protected $proxies = ['192.168.254.6'];

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_AWS_ELB;
}
