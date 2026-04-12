<?php

use App\Http\Controllers\sts\DynamoClienteController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'sts',], function () {
    // CLIENTE DYNAMO - Creación de clientes
    Route::post('/verificarIdentificacion', [DynamoClienteController::class, 'verificarIdentificacion']);
    Route::post('/addDynamoCliente', [DynamoClienteController::class, 'addDynamoCliente']);
});
