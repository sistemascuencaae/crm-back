<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google_sheets' => [
        // Opción A (recomendada): JSON completo de la cuenta de servicio,
        // codificado en Base64, en una sola línea del .env.
        'credentials_json' => env('GOOGLE_SHEETS_CREDENTIALS_JSON'),
        // Opción B: ruta a un archivo .json en disco.
        // Por defecto: storage/app/google/service-account.json
        'credentials' => env('GOOGLE_SHEETS_CREDENTIALS', storage_path('app/google/service-account.json')),
        // ID de la hoja usada por defecto (opcional). Es el tramo del URL entre /d/ y /edit.
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
    ],

    // Geocoding / reverse geocoding / POIs. Cuenta gratuita en geoapify.com.
    'geoapify' => [
        'key' => env('GEOAPIFY_API_KEY'),
        // Restringe los resultados a un país (código ISO en minúsculas).
        'pais' => env('GEOAPIFY_PAIS', 'ec'),
        // Estilo del mapa vectorial: osm-bright, positron, dark-matter, toner...
        'map_style' => env('GEOAPIFY_MAP_STYLE', 'osm-bright'),
    ],

    // Solo se usa para la vista de calle (Street View). Si se deja vacío, el
    // módulo cae al deep link de Google Maps, que no necesita credenciales.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

    // Consulta de clientes GaranCheck / Plataforma 360° (Basic Auth). Sin user/pass el
    // endpoint responde "Proveedor no configurado" sin salir a la red.
    'garancheck' => [
        'url' => env('GARANCHECK_URL'),
        'user' => env('GARANCHECK_USER'),
        'pass' => env('GARANCHECK_PASS'),
    ],

    // 30-oct: Yupana (calificador por agencia, App\Servicios\Consultas\YupanaService). Lo que se repite en todas las
    // llamadas vive en el .env; el eventType y la ruta de cada llamada van en el servicio.
    'yupana' => [
        'token_url' => env('YUPANA_TOKEN_URL'),
        'token_basic' => env('YUPANA_TOKEN_BASIC'),
        'user' => env('YUPANA_USER'),
        'pass' => env('YUPANA_PASS'),
        'url' => env('YUPANA_URL'),
        'empresa' => env('YUPANA_EMPRESA', 'YUPANA'),
        'origen' => env('YUPANA_ORIGEN'),
        'canal' => env('YUPANA_CANAL', 'api'),
        'trace_id' => env('YUPANA_TRACE_ID'),
        'cod_b2b' => env('YUPANA_COD_B2B', 'B2B001'),
        'modelo_id' => env('YUPANA_MODELO_ID', 1),
        'nombre_usuario' => env('YUPANA_NOMBRE_USUARIO', 'AACHINA'),
        'timeout' => env('YUPANA_TIMEOUT', 60),
    ],

    'firebase' => [
    'project_id'  => env('FIREBASE_PROJECT_ID'),
    'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

];
