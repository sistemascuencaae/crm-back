<?php

use App\Http\Controllers\crm\ComentariosController;
use App\Http\Controllers\crm\credito\ArchivoController;
use App\Http\Controllers\crm\credito\EtiquetaController;
use App\Http\Controllers\crm\credito\GaleriaController;
use App\Http\Controllers\crm\EntidadController;
use App\Http\Controllers\crm\FlujoController;
use App\Http\Controllers\crm\TareaController;
use App\Http\Controllers\JWTController;
use App\Http\Controllers\PruebasController;
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






//----------------------- RUTAS FELIPE ----------------------------------------------
//----------------------- RUTAS FELIPE ----------------------------------------------
//----------------------- RUTAS FELIPE ----------------------------------------------


//cambios felipe sin actualizar
Route::group(["prefix" => "crm"], function ($router) {


    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [AnalistaController::class, 'guardarComentario']);


    Route::get('/listarFlujos', [FlujoController::class, 'listarFlujos']);
    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']);
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);
    
    Route::get('/listFlujos', [FlujoController::class, 'list']);
    Route::post('/create-flujo', [FlujoController::class, 'create']);
    Route::put('/update-flujo', [FlujoController::class, 'update']);
    Route::put('/update-flujos', [FlujoController::class, 'updateFlujos']);
    Route::delete('/delete-flujo/{id}', [FlujoController::class, 'delete']);
    
    Route::get('/listTareas', [TareaController::class, 'list']);
    
    
    

    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [ComentariosController::class, 'guardarComentario']);


});

//----------------------- FIN RUTAS FELIPE ----------------------------------------------
//----------------------- FIN RUTAS FELIPE ----------------------------------------------
//----------------------- FIN RUTAS FELIPE ----------------------------------------------




//----------------------- START RUTAS JUAN  ----------------------------------------------

//Rutas Juan GALERIA
Route::group(["prefix" => "crm"], function ($router) {
    Route::post('/addGaleria', [GaleriaController::class, 'store']); // Guardar la imagen
    Route::get('/allGaleria/{id}', [GaleriaController::class, 'index']); // Listar las imagenes
    Route::post('/updateGaleria/{id}', [GaleriaController::class, 'edit']); // Edita la imagen
    Route::delete('/deleteGaleria/{id}', [GaleriaController::class, 'destroy']); // Elimina la imagen
});

//Rutas Juan ARCHIVO
Route::group(["prefix" => "crm"], function ($router) {
    Route::post('/addArchivo', [ArchivoController::class, 'store']); // Guardar
    Route::get('/allArchivo/{id}', [ArchivoController::class, 'index']); // Listar
    Route::post('/updateArchivo/{id}', [ArchivoController::class, 'edit']); // Editar
    Route::delete('/deleteArchivo/{id}', [ArchivoController::class, 'destroy']); // Eliminar
});

//Rutas Juan Etiqueta
Route::group(["prefix" => "crm"], function ($router) {
    Route::post('/addEtiqueta', [EtiquetaController::class, 'store']); // Guardar
    Route::get('/allEtiqueta/{id}', [EtiquetaController::class, 'index']); // Listar
    Route::put('/updateEtiqueta/{id}', [EtiquetaController::class, 'edit']); // Editar
    Route::delete('/deleteEtiqueta/{id}', [EtiquetaController::class, 'destroy']); // Eliminar
});

Route::group(["prefix" => "crm"], function ($router) { // Listar
    Route::get('/allEC/{cedula}', [EntidadController::class, 'list']); // Listar
});

//----------------------- END RUTAS JUAN  ----------------------------------------------