<?php

use App\Http\Controllers\crm\ComentariosController;
use App\Http\Controllers\crm\credito\AnalistaController;
use App\Http\Controllers\crm\FlujoController;
use App\Http\Controllers\crm\TareaController;
use App\Http\Controllers\JWTController;
use App\Http\Controllers\User\ProfileUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => 'api'], function($router) {

    Route::post('/register', [JWTController::class, 'register']);
    Route::post('/login', [JWTController::class, 'login']);
    Route::post('/profile', [JWTController::class, 'profile']);

    



    //Route::post('/register', "JWTController@register");
    //Route::post('/login', "JWTController@login");
    // Route::post('/logout', "JWTController@logout");
    // Route::post('/refresh', "JWTController@refresh");
    // Route::post('/profile', "JWTController@profile");
});

// Route::group(['prefix'=>'users'].function($router) {
//     Route::post('/profile-user','JWTController@profile');
// });


Route::group(['middleware' => 'api','prefix' => 'users'],function($router){
    Route::post('/profile-user', [ProfileUserController::class, 'profile_user']);
});






//----------------------- C R M   ----------------------------------------------





Route::group(["prefix" => "crm"],function($router){
    Route::get('/pruebacambiodiv', [AnalistaController::class, 'pruebacambiodiv']);
    Route::post('/actulizarDatoDiv', [AnalistaController::class, 'actulizarDatoDiv']);
    Route::post('/pruebacambiodivDos', [AnalistaController::class, 'pruebacambiodivDos']);//pruebacambiodivDos
    Route::post('/updateDiv', [AnalistaController::class, 'updateDiv']);
    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);//listaComentarios
    Route::post('/guardarComentario', [AnalistaController::class, 'guardarComentario']);//guardarComentario
    Route::get('/listarFlujos', [FlujoController::class, 'listarFlujos']);
    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']);//
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);
});

//cambios felipe sin actualizar
Route::group(["prefix" => "crm"],function($router){
    Route::get('/listarFlujos', [FlujoController::class, 'listarFlujos']);
    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']);
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);

});


Route::group(["prefix" => "layout"],function($router){
    Route::get('/listarFlujos', [FlujoController::class, 'listarFlujos']);
});




