<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\FormOcupaController;
use App\Http\Controllers\FormularioController;
use App\Http\Controllers\GaleriaController;
use App\Http\Controllers\FormAptiMedicaController;
use App\Http\Controllers\FormConsumoDrogasController;
use App\Http\Controllers\hclinico\FormGaleriaController;
use App\Http\Controllers\hclinico\FormPeriodico;
use App\Http\Controllers\hclinico\PacienteDosController;
use App\Http\Controllers\PruebasApi;
use App\Http\Controllers\ReporteFormularios;




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

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
});


Route::group([
    'prefix' => 'paciente',
], function () {
    Route::get('all', [PacienteController::class, 'all']);
    Route::get('list', [PacienteController::class, 'list']);
    Route::get('search', [PacienteController::class, 'search']);
    Route::get('byId/{id}', [PacienteController::class, 'byId']);
    Route::post('create', [PacienteController::class, 'create']);
    Route::put('update', [PacienteController::class, 'update']);
    Route::delete('delete/{id}', [PacienteController::class, 'delete']);
    Route::get('getImage/{filename}', [PacienteController::class, 'getImage']);
    Route::post('addImage', [PacienteController::class, 'addImage']);
});

Route::group([
    'prefix' => 'paciente-dos',
], function () {
    Route::get('byIdentificacion/{ident}/{pacId}', [PacienteDosController::class, 'byIdentificacion']);
    Route::put('edit/{id}', [PacienteDosController::class, 'edit']);
    Route::post('add', [PacienteDosController::class, 'add']);
});

Route::group([
    'prefix' => 'ocupacional',
], function () {
    Route::post('guardarImagen', [FormOcupaController::class, 'guardarImagen']);
    Route::post('getImage', [FormOcupaController::class, 'getImage']);
    Route::post('addImage', [FormOcupaController::class, 'addImage']);
    Route::post('create', [FormOcupaController::class, 'create']);
    Route::put('update', [FormOcupaController::class, 'update']);
    Route::get('byId/{id}', [FormOcupaController::class, 'byId']);
    Route::get('all', [FormOcupaController::class, 'all']);
    Route::get('allActive', [FormOcupaController::class, 'allActive']);
    Route::get('todasImagenes/{formulario_id}', [FormOcupaController::class, 'todasImagenes']);

    // Nueva version de imagenes
    Route::get('imagenesFormulario/{formId}', [FormGaleriaController::class, 'imagenesFormulario']);
    Route::post('addGaleriaForm/{formId}', [FormGaleriaController::class, 'addGaleriaForm']);
    Route::post('editGaleriaForm/{formId}', [FormGaleriaController::class, 'editGaleriaForm']);//
});

Route::group([
    'prefix' => 'aptitudmedica',
], function () {
    Route::post('create', [FormAptiMedicaController::class, 'create']);
    Route::put('update', [FormAptiMedicaController::class, 'update']);
    Route::get('byId/{id}', [FormAptiMedicaController::class, 'byId']);
    Route::get('all', [FormAptiMedicaController::class, 'all']);
});

Route::group(['prefix' => 'consumodroga',], function () {
    Route::post('addFormConsumoDrogas', [FormConsumoDrogasController::class, 'addFormConsumoDrogas']);
    Route::get('listParametros', [FormConsumoDrogasController::class, 'listParametros']); //pacienteFormConsumoDro
    Route::get('pacienteFormConsumoDro', [FormConsumoDrogasController::class, 'pacienteFormConsumoDro']);//
});

Route::group([
    'prefix' => 'formulario',
], function () {
    Route::get('all', [FormularioController::class, 'all']);
    Route::get('byId/{id}', [FormularioController::class, 'byId']);
});

Route::group([
    'prefix' => 'form-periodico',
], function () {
    Route::get('store/{identificacion}/{pacId}', [FormPeriodico::class, 'store']);
});



Route::group([
    'prefix' => 'galeria',
], function () {
    Route::get('all/{tipo_formulario}/{id}', [GaleriaController::class, 'all']);
    Route::get('byId/{id}', [GaleriaController::class, 'byId']);
    Route::post('create', [GaleriaController::class, 'create']);
    Route::put('update', [GaleriaController::class, 'update']);
    Route::delete('delete/{id}', [GaleriaController::class, 'delete']);
    Route::get('getImage/{filename}', [GaleriaController::class, 'getImage']);
    Route::post('addImage', [GaleriaController::class, 'addImage']);

});



Route::group([
    'prefix' => 'documentos',
], function(){
    Route::get('/reporteFAM/{id}', [ReporteFormularios::class, 'documentFAM']);
    Route::get('/reporteFO/{id}', [ReporteFormularios::class, 'documentFO']);
});

Route::group([
    'prefix' => 'pruebasApi',
], function(){
    //Route::get('/prueba', [PruebasApi::class, 'prueba']);
});








