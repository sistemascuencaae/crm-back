<?php

use App\Http\Controllers\sts\DynamoClienteController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'sts',], function () {
    // CLIENTE DYNAMO
    Route::post('/verificarClienteDynamo', [DynamoClienteController::class, 'verificarClienteDynamo']);
    Route::post('/addDynamoCliente', [DynamoClienteController::class, 'addDynamoCliente']);
});
