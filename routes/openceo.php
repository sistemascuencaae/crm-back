<?php

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
    Route::get('agencias', [OpenceoController::class, 'agencias']);
    Route::get('departamentos', [OpenceoController::class, 'departamentos']);
    Route::get('ciudades', [OpenceoController::class, 'ciudades']);
    Route::get('cargos', [OpenceoController::class, 'cargos']);
    Route::get('v_dmovinv', [OpenceoController::class, 'v_dmovinv']);
});
