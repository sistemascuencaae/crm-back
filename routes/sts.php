<?php

use App\Http\Controllers\sts\ArchivoStsController;
use App\Http\Controllers\sts\DynamoClienteController;
use Illuminate\Support\Facades\Route;

// Rutas PROTEGIDAS (requieren token JWT del usuario/proveedor)
Route::group(["prefix" => "sts", 'middleware' => ['jwt.auth', 'usuario.activo']], function ($router) {
    // CLIENTE DYNAMO: el proveedor pide el link firmado con sus credenciales
    Route::post('/generarLinkFormulario', [DynamoClienteController::class, 'generarLinkFormulario']);
    // CLIENTE DYNAMO: listado de clientes con su corredor (filtro opcional por corredor)
    Route::post('/listClientesCorredor', [DynamoClienteController::class, 'listClientesCorredor']);

    // ARCHIVOS
    Route::post('/addArchivos', [ArchivoStsController::class, 'addArchivos']);
    Route::post('/listArchivos', [ArchivoStsController::class, 'listArchivos']);
    Route::post('/deleteArchivo', [ArchivoStsController::class, 'deleteArchivo']);
});

// Rutas PÚBLICAS del formulario Dynamo (sin login).
// Protegidas por token cifrado (Crypt, validado en el controlador) + rate limit.
// throttle:10,1 = máx 10 peticiones por minuto por IP.
Route::group(["prefix" => "sts", 'middleware' => ['throttle:10,1']], function ($router) {
    Route::post('/validarEnlace', [DynamoClienteController::class, 'validarEnlace']);
    Route::post('/addDynamoCliente', [DynamoClienteController::class, 'addDynamoCliente']);
    Route::post('/verificarClienteDynamo', [DynamoClienteController::class, 'verificarClienteDynamo']);
    Route::post('/updateDynamoCliente', [DynamoClienteController::class, 'updateDynamoCliente']);
});
