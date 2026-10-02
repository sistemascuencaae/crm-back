<?php

use App\Http\Controllers\demo\DemoController;
use App\Http\Controllers\demo\GeolocalizacionController;
use App\Http\Controllers\demo\PlantillaController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'demo',
], function () {
    // SUBIDA DATOS EXCEL
    Route::post('/addDatos', [DemoController::class, 'addDatos']);
    Route::get('/diferenciasFechasDynamoNovasoft', [DemoController::class, 'diferenciasFechasDynamoNovasoft']);

    // PLANTILLA DINÁMICA
    Route::middleware(['jwt.auth', 'usuario.activo'])->group(function () {
        Route::get('/plantilla/caso/{id}', [PlantillaController::class, 'casoVariables']);
    });

    // GEOLOCALIZACIÓN (proxy a Geoapify + Street View)
    Route::middleware(['jwt.auth', 'usuario.activo'])->prefix('geo')->group(function () {
        Route::get('/estilo',       [GeolocalizacionController::class, 'estilo']);
        Route::get('/autocomplete', [GeolocalizacionController::class, 'autocomplete']);
        Route::get('/buscar',       [GeolocalizacionController::class, 'buscar']);
        Route::get('/reverse',      [GeolocalizacionController::class, 'reverse']);
        Route::get('/cerca',        [GeolocalizacionController::class, 'cerca']);
        Route::get('/streetview',   [GeolocalizacionController::class, 'streetview']);
    });
});
