<?php

use App\Http\Controllers\comercializacion\CliReiterativoController;
use App\Http\Controllers\comercializacion\ComercializacionController;
use App\Http\Controllers\openceo\BodegaController;
use App\Http\Controllers\openceo\TipoProductoController;
use App\Http\Controllers\openceo\UserDynamoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OpenceoController;

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
    'prefix' => 'open',
], function () {
    Route::get('/getTiposProducto', [TipoProductoController::class, 'getTiposProducto']);
    Route::get('agencias', [OpenceoController::class, 'agencias']);
    Route::get('departamentos', [OpenceoController::class, 'departamentos']);
    Route::get('comprobantes', [OpenceoController::class, 'comprobantes']);
    Route::get('ciudades', [OpenceoController::class, 'ciudades']);
    Route::get('cargos', [OpenceoController::class, 'cargos']);
    Route::get('v_dmovinv', [OpenceoController::class, 'v_dmovinv']);
    Route::get('/storeVentas', [ComercializacionController::class, 'storeVentas']);
    Route::post('/ventasAlmacen', [ComercializacionController::class, 'ventasAlmacen']);
    Route::post('/ventasZonales', [ComercializacionController::class, 'ventasZonales']);
    Route::post('/ventasTotales', [ComercializacionController::class, 'ventasTotales']);
    Route::post('/ventasAlmacenesPeriodos', [ComercializacionController::class, 'ventasAlmacenesPeriodos']);
    Route::post('/ventasPorAgente', [ComercializacionController::class, 'ventasPorAgente']);
    //CliReiterativoController
    Route::get('/getCliReiIdenti/{identificacion}',[CliReiterativoController::class, 'getByIdentificacionCliReitera']);
    //Route::get('/comprobantesCliReiterativo/{identificacion}/{comprobante}/{page}/{itemsPerPage}', [CliReiterativoController::class, 'comprobantesCliReiterativo']);
    Route::get('/comprobantesCliReiterativo/{comprobante}', [CliReiterativoController::class, 'comprobantesCliReiterativo']);
    Route::post('/comproClienReitera', [CliReiterativoController::class, 'comproClienReitera']);
    //---formularios dinamicos
    Route::get('/getTiposComprobantesAlmacenes', [ComercializacionController::class, 'getTiposComprobantesAlmacenes']);
    Route::get('/getComprobantes/{ctiId}/{almId}', [ComercializacionController::class, 'getComprobantes']);


    // CLIENTE REITERATIVO V2 
    Route::get('/getClienteReiterativoByIdentificacion/{identificacion}', [CliReiterativoController::class, 'getClienteReiterativoByIdentificacion']);
    Route::get('/getInfoCuotasByComprobante/{comprobante}', [CliReiterativoController::class, 'getInfoCuotasByComprobante']);
    Route::get('/getInfoCobrosByComprobante/{comprobante}/{cuota}', [CliReiterativoController::class, 'getInfoCobrosByComprobante']);
    
    
    
    // USUARIOS OPENCEO
    Route::get('/listAllUsuariosActivos', [UserDynamoController::class, 'listAllUsuariosActivos']);
    Route::get('/listAlmacenesActivos', [UserDynamoController::class, 'listAlmacenesActivos']);
    Route::post('/editUsuarioPuntoVenta', [UserDynamoController::class, 'editUsuarioPuntoVenta']);
    
    
    // BODEGAS USUARIOS
    Route::get('/listBodegasUsuarios', [BodegaController::class, 'listBodegasUsuarios']);
    Route::get('/listAllUsuActivos', [BodegaController::class, 'listAllUsuActivos']);
    
    Route::get('/listBodegasDynamo', [BodegaController::class, 'listBodegasDynamo']);
    Route::get('/listUsuariosByBodIdDynamo/{bod_id}', [BodegaController::class, 'listUsuariosByBodIdDynamo']);
    Route::post('/editUsuBodegas', [BodegaController::class, 'editUsuBodegas']);
});
