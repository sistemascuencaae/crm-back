<?php

use App\Http\Controllers\crm\BitacoraController;
use App\Http\Controllers\crm\CasoController;
use App\Http\Controllers\crm\ComentariosController;
use App\Http\Controllers\crm\credito\ArchivoController;
use App\Http\Controllers\crm\credito\EtiquetaController;
use App\Http\Controllers\crm\credito\GaleriaController;
use App\Http\Controllers\crm\credito\TipoGaleriaController;
use App\Http\Controllers\crm\DepartamentoController;
use App\Http\Controllers\crm\EntidadController;
use App\Http\Controllers\crm\FaseController;
use App\Http\Controllers\crm\FlujoController;
use App\Http\Controllers\crm\NotaController;
use App\Http\Controllers\crm\TableroController;
use App\Http\Controllers\crm\TareaController;
use App\Http\Controllers\crm\TipoTableroController;
use App\Http\Controllers\User\UsersOpenceoController;
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






//----------------------- RUTAS FELIPE ----------------------------------------------
//----------------------- RUTAS FELIPE ----------------------------------------------
//----------------------- RUTAS FELIPE ----------------------------------------------


//cambios felipe sin actualizar
Route::group(["prefix" => "crm"], function ($router) {


    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [AnalistaController::class, 'guardarComentario']);


    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']);
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);

    Route::get('/listFlujos', [FlujoController::class, 'list']);
    Route::post('/create-flujo', [FlujoController::class, 'create']);
    Route::put('/update-flujo', [FlujoController::class, 'update']);
    Route::put('/update-flujos', [FlujoController::class, 'updateFlujos']);
    Route::delete('/delete-flujo/{id}', [FlujoController::class, 'delete']);


    //------------------------------------------------------------------>FASE
    Route::get('/listFase', [FaseController::class, 'list']);
    // Route::post('/create-flujo', [FlujoController::class, 'create']);
    // Route::put('/update-flujo', [FlujoController::class, 'update']);
    // Route::put('/update-flujos', [FlujoController::class, 'updateFlujos']);
    // Route::delete('/delete-flujo/{id}', [FlujoController::class, 'delete']);

    //------------------------------------------------------------------>CASO
    Route::put('/editCaso', [CasoController::class, 'edit']);




    Route::post('/addTarea', [TareaController::class, 'add']);
    Route::get('/listTareas', [TareaController::class, 'list']);
    Route::get('/byCedula/{cedula}', [EntidadController::class, 'byCedula']); // Listar




    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [ComentariosController::class, 'guardarComentario']);

    Route::get('/listAnalistas', [UsersOpenceoController::class, 'listAnalistas']);
    Route::get('/listUsuariosActivos', [UsersOpenceoController::class, 'listUsuariosActivos']);


});

//----------------------- FIN RUTAS FELIPE ----------------------------------------------
//----------------------- FIN RUTAS FELIPE ----------------------------------------------
//----------------------- FIN RUTAS FELIPE ----------------------------------------------



//----------------------- START RUTAS JUAN  ----------------------------------------------

Route::group(["prefix" => "crm"], function ($router) {

    // GALERIA

    Route::post('/addGaleria', [GaleriaController::class, 'addGaleria']); // Guardar la imagen
    Route::get('/listGaleriaByCasoId/{id}', [GaleriaController::class, 'listGaleriaByCasoId']); // Listar las imagenes
    Route::post('/updateGaleria/{id}', [GaleriaController::class, 'updateGaleria']); // Edita la imagen
    Route::delete('/deleteGaleria/{id}', [GaleriaController::class, 'deleteGaleria']); // Elimina la imagen

    Route::get('/allTipoGaleria', [TipoGaleriaController::class, 'allTipoGaleria']); // Listar los tipos de imagenes

    // ARCHIVO

    Route::post('/addArchivo', [ArchivoController::class, 'addArchivo']); // Guardar
    Route::get('/listArchivoByCasoId/{id}', [ArchivoController::class, 'listArchivoByCasoId']); // Listar
    Route::post('/updateArchivo/{id}', [ArchivoController::class, 'updateArchivo']); // Editar
    Route::delete('/deleteArchivo/{id}', [ArchivoController::class, 'deleteArchivo']); // Eliminar

    // Etiqueta

    Route::post('/addEtiqueta', [EtiquetaController::class, 'addEtiqueta']); // Guardar
    Route::get('/listEtiquetaByCasoId/{id}', [EtiquetaController::class, 'listEtiquetaByCasoId']); // Listar
    // Route::put('/updateEtiqueta/{id}', [EtiquetaController::class, 'updateEtiqueta']); // Editar
    Route::delete('/deleteEtiqueta/{id}', [EtiquetaController::class, 'deleteEtiqueta']); // Eliminar

    // ENTIDAD

    Route::get('/searchById/{id}', [EntidadController::class, 'searchById']); // Listar
    Route::get('/searchByCedula/{id}', [EntidadController::class, 'searchByCedula']); // Listar
    Route::post('/updateEntidad', [EntidadController::class, 'updateEntidad']); // Editar

    // BITACORA

    Route::get('/listBitacoraByCasoId/{id}', [BitacoraController::class, 'listBitacoraByCasoId']); // Listar

    // TABLERO

    Route::post('/addTablero', [TableroController::class, 'addTablero']); // guardar
    Route::get('/listTableroByUser', [TableroController::class, 'listTableroByUser']); // listar
    Route::post('/updateTablero/{id}', [TableroController::class, 'updateTablero']); // Editar

    // DEPARTAMENTO

    Route::get('/allDepartamento', [DepartamentoController::class, 'allDepartamento']); // listar

    // TIPO_TABLERO

    Route::get('/allTipoTablero', [TipoTableroController::class, 'allTipoTablero']); // listar

    // NOTAS

    Route::post('/addNota', [NotaController::class, 'addNota']); // guardar
    Route::get('/listNotaByCasoId/{id}', [NotaController::class, 'listNotaByCasoId']); // listar
    Route::post('/updateNota/{id}', [NotaController::class, 'updateNota']); // Editar
    Route::delete('/deleteNota/{id}', [NotaController::class, 'deleteNota']); // Eliminar

});

//----------------------- END RUTAS JUAN  ----------------------------------------------
