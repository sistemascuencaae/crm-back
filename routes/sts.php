<?php

use App\Http\Controllers\sts\ArchivoStsController;
use App\Http\Controllers\sts\DynamoClienteController;
use Illuminate\Support\Facades\Route;

// Route::group(['prefix' => 'sts', 'middleware' => 'auth:api'], function () {
Route::group(["prefix" => "sts", 'middleware' => ['jwt.auth', 'usuario.activo']], function ($router) {
    // CLIENTE DYNAMO
    Route::post('/verificarClienteDynamo', [DynamoClienteController::class, 'verificarClienteDynamo']);
    Route::post('/addDynamoCliente', [DynamoClienteController::class, 'addDynamoCliente']);

    // ARCHIVOS
    Route::post('/addArchivos', [ArchivoStsController::class, 'addArchivos']);
    Route::post('/listArchivos', [ArchivoStsController::class, 'listArchivos']);
    Route::post('/deleteArchivo', [ArchivoStsController::class, 'deleteArchivo']);
});
