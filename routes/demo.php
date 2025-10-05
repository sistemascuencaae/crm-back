<?php

use App\Http\Controllers\demo\DemoController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'demo',
], function () {
    // SUBIDA DATOS EXCEL
    Route::post('/addDatos', [DemoController::class, 'addDatos']);

});
