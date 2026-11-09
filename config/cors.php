<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'api/broadcasting/auth', 'hcli/*', 'openceo/*', 'demo/*', 'sts/*', 'garancheck/*', 'web/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // DESACTIVADO 07-nov: 'exposed_headers' => [],
    // 07-nov: el front (otro origen) lee el usuario actual de esta cabecera en cada respuesta (middleware CabeceraSesionUsuario)
    'exposed_headers' => ['X-Sesion-Usuario'],

    'max_age' => 0,

    'supports_credentials' => false,

];
