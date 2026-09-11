<?php

use App\Http\Controllers\garancheck\GarancheckController;
use Illuminate\Support\Facades\Route;

// Rutas PROTEGIDAS (requieren token JWT del usuario/proveedor)
Route::group(["prefix" => "", 'middleware' => ['jwt.auth', 'usuario.activo']], function ($router) {
    Route::post('/consultar_cliente_motor_cognitivo', [GarancheckController::class, 'consultar_cliente_motor_cognitivo']);
});
