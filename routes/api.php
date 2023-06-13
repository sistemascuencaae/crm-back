<?php

use App\Http\Controllers\crm\ComentariosController;
use App\Http\Controllers\crm\credito\ArchivoController;
use App\Http\Controllers\crm\credito\EtiquetaController;
use App\Http\Controllers\crm\credito\GaleriaController;
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

Route::group(['middleware' => 'api'], function ($router) {

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


Route::group(['middleware' => 'api', 'prefix' => 'users'], function ($router) {
    Route::post('/profile-user', [ProfileUserController::class, 'profile_user']);
});






//----------------------- C R M   ----------------------------------------------


//cambios felipe sin actualizar
Route::group(["prefix" => "crm"], function ($router) {


    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']); //listaComentarios
    Route::post('/guardarComentario', [AnalistaController::class, 'guardarComentario']); //guardarComentario


    Route::get('/listarFlujos', [FlujoController::class, 'listarFlujos']);
    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']); //
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);
});

    Route::post('/create-flujo', [FlujoController::class, 'create']);
    Route::put('/update-flujo', [FlujoController::class, 'update']);
    Route::put('/update-flujos', [FlujoController::class, 'updateFlujos']);
    Route::get('/list-flujo', [FlujoController::class, 'list']);
    Route::delete('/delete-flujo/{id}', [FlujoController::class, 'delete']);//


    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']);
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);

    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);//listaComentarios
    Route::post('/guardarComentario', [ComentariosController::class, 'guardarComentario']);//guardarComentario

});






//Rutas Juan GALERIA
Route::group(["prefix" => "crm"], function ($router) {
    Route::post('/add', [GaleriaController::class, 'store']); // Guardar la imagen
    Route::get('/all', [GaleriaController::class, 'index']); // Listar las imagenes
    Route::post('/update/{id}', [GaleriaController::class, 'edit']); // Edita la imagen
    Route::delete('/delete/{id}', [GaleriaController::class, 'destroy']); // Elimina la imagen
});

//Rutas Juan ARCHIVO
Route::group(["prefix" => "crm"], function ($router) {
    Route::post('/addArchivo', [ArchivoController::class, 'store']); // Guardar
    Route::get('/allArchivo', [ArchivoController::class, 'index']); // Listar
    Route::post('/updateArchivo/{id}', [ArchivoController::class, 'edit']); // Editar
    Route::delete('/deleteArchivo/{id}', [ArchivoController::class, 'destroy']); // Eliminar
});

//Rutas Juan ARCHIVO
Route::group(["prefix" => "crm"], function ($router) {
    Route::post('/addEtiqueta', [EtiquetaController::class, 'store']); // Guardar
    Route::get('/allEtiqueta', [EtiquetaController::class, 'index']); // Listar
    Route::put('/updateEtiqueta/{id}', [EtiquetaController::class, 'edit']); // Editar
    Route::delete('/deleteEtiqueta/{id}', [EtiquetaController::class, 'destroy']); // Eliminar
});



// CAMBIO FELIPE PRUEBA