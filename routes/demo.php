<?php

use App\Http\Controllers\demo\DemoController;
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
});
