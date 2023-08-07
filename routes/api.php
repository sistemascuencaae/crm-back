<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\crm\BitacoraController;
use App\Http\Controllers\crm\CActividadController;
use App\Http\Controllers\crm\CasoController;
use App\Http\Controllers\crm\ClienteOpenceoController;
use App\Http\Controllers\crm\ComentariosController;
use App\Http\Controllers\crm\CondicionesController;
use App\Http\Controllers\crm\credito\ArchivoController;
use App\Http\Controllers\crm\credito\EtiquetaController;
use App\Http\Controllers\crm\credito\GaleriaController;
use App\Http\Controllers\crm\credito\solicitudCreditoController;
use App\Http\Controllers\crm\credito\TipoGaleriaController;
use App\Http\Controllers\crm\CrmController;
use App\Http\Controllers\crm\CTareaController;
use App\Http\Controllers\crm\CTipoResultadoCierreController;
use App\Http\Controllers\crm\DActividadController;
use App\Http\Controllers\crm\DepartamentoController;
use App\Http\Controllers\crm\EntidadController;
use App\Http\Controllers\crm\FaseController;
use App\Http\Controllers\crm\FlujoController;
use App\Http\Controllers\crm\NotaController;
use App\Http\Controllers\crm\NotificacionesController;
use App\Http\Controllers\crm\RequerimientoController;
use App\Http\Controllers\crm\TableroController;
use App\Http\Controllers\crm\TareaController;
use App\Http\Controllers\crm\TipoCasoController;
use App\Http\Controllers\crm\TipoTableroController;
use App\Http\Controllers\User\UserController;
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
    Route::put('/profile', [ProfileUserController::class, 'profile_user']);
});






//----------------------- RUTAS FELIPE ----------------------------------------------
//----------------------- RUTAS FELIPE ----------------------------------------------
//----------------------- RUTAS FELIPE ----------------------------------------------


//cambios felipe sin actualizar
Route::group(["prefix" => "crm"], function ($router) {
    //CRM CONTROLLER PRINCIPAL
    Route::get('/crmTablero/{id}', [CrmController::class, 'list']);
    //notificaciones
    Route::post('/addNotificacion', [NotificacionesController::class, 'add']);

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
    Route::post('/listFase', [FaseController::class, 'list']);
    Route::post('/addFase', [FaseController::class, 'add']);
    Route::put('/editFase', [FaseController::class, 'edit']);
    // Route::put('/update-flujos', [FlujoController::class, 'updateFlujos']);
    // Route::delete('/delete-flujo/{id}', [FlujoController::class, 'delete']);

    //------------------------------------------------------------------>CASO
    Route::put('/editCasoFase', [CasoController::class, 'editFase']);
    Route::post('/addCaso', [CasoController::class, 'add']);
    Route::put('/bloqueoCaso', [CasoController::class, 'bloqueoCaso']);
    Route::get('/casoById/{id}', [CasoController::class, 'casoById']);
    Route::put('/editCaUsAs/{id}', [CasoController::class, 'editCasosUsuarioAsignado']);
    Route::get('/depUserTablero/{casoId}', [CasoController::class, 'depUserTablero']); //





    Route::post('/addTarea', [TareaController::class, 'add']);
    Route::get('/listTareas', [TareaController::class, 'list']);
    Route::get('/byCedula/{cedula}', [EntidadController::class, 'byCedula']); // Listar




    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [ComentariosController::class, 'guardarComentario']);

    Route::get('/listAnalistas/{tableroId}', [UserController::class, 'listAnalistas']);
    Route::get('/listUsuariosActivos', [UserController::class, 'listUsuariosActivos']);

    /************************  OPENCEO   *********************** */

    Route::get('/clienteByCedula/{cedula}', [ClienteOpenceoController::class, 'byCedula']);
    Route::get('/listClientes/{parametro}', [ClienteOpenceoController::class, 'list']);

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
    Route::get('/searchByCedula/{cedula}', [EntidadController::class, 'searchByCedula']); // Listar
    Route::post('/updateEntidad', [EntidadController::class, 'updateEntidad']); // Editar

    // BITACORA

    Route::get('/listBitacoraByCasoId/{id}', [BitacoraController::class, 'listBitacoraByCasoId']); // Listar

    // TABLERO

    Route::post('/addTablero', [TableroController::class, 'addTablero']); // guardar
    Route::get('/listTableroByUser/{user_id}', [TableroController::class, 'listTableroByUser']); // listar
    Route::get('/listTableroMisCasos/{user_id}', [TableroController::class, 'listTableroMisCasos']); // listar tablero mis casos
    Route::post('/updateTablero/{id}', [TableroController::class, 'updateTablero']); // Editar
    Route::get('/listAllTableros', [TableroController::class, 'listAll']); // listar tablero mis casos
    Route::get('/listAllTablerosActivos', [TableroController::class, 'listAllTablerosActivos']); // listar tableros inactivos
    Route::get('/listAllTablerosInactivos', [TableroController::class, 'listAllTablerosInactivos']); // listar tableros inactivos

    // DEPARTAMENTO

    Route::get('/allDepartamento', [DepartamentoController::class, 'allDepartamento']); // listar
    Route::get('/listDepAllUser', [DepartamentoController::class, 'listAllUser']); // listar

    // TIPO_TABLERO

    Route::get('/allTipoTablero', [TipoTableroController::class, 'allTipoTablero']); // listar

    // NOTAS

    Route::post('/addNota', [NotaController::class, 'addNota']); // guardar
    Route::get('/listNotaByCasoId/{id}', [NotaController::class, 'listNotaByCasoId']); // listar
    Route::post('/updateNota/{id}', [NotaController::class, 'updateNota']); // Editar
    Route::delete('/deleteNota/{id}', [NotaController::class, 'deleteNota']); // Eliminar

    // CASO

    Route::get('/listCasoById/{id}', [CasoController::class, 'listCasoById']); // listar
    Route::post('/editPrioridadCaso/{id}', [CasoController::class, 'editPrioridadCaso']);
    Route::post('/editarTipoCaso/{id}', [CasoController::class, 'editarTipoCaso']);

    // CTAREA

    Route::post('/addCTarea', [CTareaController::class, 'addCTarea']); // guardar
    Route::get('/listTareasByIdTablero/{tab_id}', [CTareaController::class, 'listTareasByIdTablero']); // guardar
    Route::post('/updateCTarea/{id}', [CTareaController::class, 'updateCTarea']); // Edita la tarea

    // CACTIVIDAD

    // Route::post('/addCActividad', [CActividadController::class, 'addCActividad']); // guardar
    Route::post('/addCTipoActividad', [CActividadController::class, 'addCTipoActividad']); // guardar
    Route::get('listCTipoActividadByIdTablero/{tab_id}', [CActividadController::class, 'listCTipoActividadByIdTablero']); // listar
    Route::get('listCTipoActividadByIdTableroEstadoActivo/{tab_id}', [CActividadController::class, 'listCTipoActividadByIdTableroEstadoActivo']); // listar
    Route::get('listCTipoActividadByIdCasoId/{caso_id}', [CActividadController::class, 'listCTipoActividadByIdCasoId']); // listar
    // Route::get('allCTipoActividades', [CActividadController::class, 'allCTipoActividades']); // listar todo
    Route::post('/editCTipoActividad/{id}', [CActividadController::class, 'editCTipoActividad']); // Edita la actividad
    Route::delete('/deleteCTipoActividad/{id}', [CActividadController::class, 'deleteCTipoActividad']); // Eliminar

    // DACTIVIDAD

    Route::post('/addDTipoActividad', [DActividadController::class, 'addDTipoActividad']); // guardar
    Route::get('listActividadesByIdCasoId/{caso_id}', [DActividadController::class, 'listActividadesByIdCasoId']); // listar
    Route::post('/updateDActividad/{id}', [DActividadController::class, 'updateDActividad']); // Edita la actividad
    // Route::delete('/deleteCTipoActividad/{id}', [DActividadController::class, 'deleteCTipoActividad']); // Eliminar

    // CTIPORESULTADOCIERRE

    Route::post('/addCTipoResultadoCierre', [CTipoResultadoCierreController::class, 'addCTipoResultadoCierre']); // guardar
    Route::get('listCTipoResultadoCierreByIdTablero/{tab_id}', [CTipoResultadoCierreController::class, 'listCTipoResultadoCierreByIdTablero']); // listar
    Route::get('listCTipoResultadoCierreByIdTableroEstadoActivo/{tab_id}', [CTipoResultadoCierreController::class, 'listCTipoResultadoCierreByIdTableroEstadoActivo']); // listar
    Route::get('listCTipoResultadoCierreByIdCasoId/{caso_id}', [CTipoResultadoCierreController::class, 'listCTipoResultadoCierreByIdCasoId']); // listar
    Route::post('/editCTipoResultadoCierre/{id}', [CTipoResultadoCierreController::class, 'editCTipoResultadoCierre']); // Edita la actividad
    Route::delete('/deleteCTipoResultadoCierre/{id}', [CTipoResultadoCierreController::class, 'deleteCTipoResultadoCierre']); // Eliminar

    // CHAT GRUPAL

    Route::post('/addChatGrupal', [ChatController::class, 'addChatGrupal']); // guardar
    Route::get('/listChatByCasoId/{caso_id}', [ChatController::class, 'listChatByCasoId']); // by casi_id
    Route::post('/editChatGrupal/{id}', [ChatController::class, 'editChatGrupal']); // Editar

    // TIPO CASO

    Route::post('/addTipoCaso', [TipoCasoController::class, 'addTipoCaso']); // guardar
    Route::get('listTipoCasoByIdTablero/{tab_id}', [TipoCasoController::class, 'listTipoCasoByIdTablero']); // listar
    Route::get('listTipoCasoByIdTableroEstadoActivo/{tab_id}', [TipoCasoController::class, 'listTipoCasoByIdTableroEstadoActivo']); // listar
    Route::get('listTipoCasoByIdCasoId/{caso_id}', [TipoCasoController::class, 'listTipoCasoByIdCasoId']); // listar
    Route::post('/editTipoCaso/{id}', [TipoCasoController::class, 'editTipoCaso']); // Edita la actividad
    Route::delete('/deleteTipoCaso/{id}', [TipoCasoController::class, 'deleteTipoCaso']); // Eliminar

    // TAREAS INDIVIDUALES

    Route::get('listTareasCasoById/{caso_id}', [TareaController::class, 'listTareasCasoById']); // by caso_id
    Route::post('/addTareas', [TareaController::class, 'addTareas']); // guardar
    Route::post('/editTareas/{id}', [TareaController::class, 'editTareas']); // Editar
    Route::delete('/deleteTareas/{id}', [TareaController::class, 'deleteTareas']); // Eliminar

    // MIEMBROS DEL CASO

    Route::get('listMiembrosCasoById/{caso_id}', [CasoController::class, 'listMiembrosCasoById']); // by caso_id
    Route::post('/editMiembrosCaso/{id}', [CasoController::class, 'editMiembrosCaso']); // Editar

    // USUARIOS

    Route::get('allUsers', [UserController::class, 'allUsers']); // by caso_id
    Route::post('/addUser', [UserController::class, 'addUser']); // guardar
    Route::post('/editUser/{id}', [UserController::class, 'editUser']); // Editar
    Route::delete('/deleteUser/{id}', [UserController::class, 'deleteUser']); // Eliminar

    // NOTIFICACIONES

    Route::get('/allByDepartamento/{id}', [NotificacionesController::class, 'allByDepartamento']);
    Route::get('/listByDepartamento/{id}', [NotificacionesController::class, 'listByDepartamento']);
    Route::post('/editLeidoNotificacion/{id}', [NotificacionesController::class, 'editLeidoNotificacion']);

    // REQUERIMIENTOS

    Route::get('listRequerimientosCasoById/{caso_id}', [RequerimientoController::class, 'listRequerimientosCasoById']); // by caso_id
    Route::post('/addRequerimientos', [RequerimientoController::class, 'addRequerimientos']); // guardar
    Route::post('/editRequerimientos/{id}', [RequerimientoController::class, 'editRequerimientos']); // Editar
    Route::delete('/deleteRequerimientos/{id}', [RequerimientoController::class, 'deleteRequerimientos']); // Eliminar

    // CONDICIONES

    Route::get('/listCondiciones', [CondicionesController::class, 'listCondiciones']); // all
});

Route::group(["prefix" => "credito"], function ($router) {

    // SOLICITUD CREDITO

    Route::post('/addSolicitudCredito', [solicitudCreditoController::class, 'addSolicitudCredito']); // Guardar
    Route::get('/listSolicitudCreditoByEntidadId/{id}', [solicitudCreditoController::class, 'listSolicitudCreditoByEntidadId']); // Listar por entidad ID
    Route::get('/listSolicitudCreditoByRucCedula/{cedula}', [solicitudCreditoController::class, 'listSolicitudCreditoByRucCedula']); // Listar por cedula
    // Route::get('/listSolicitudCreditoById/{id}', [solicitudCreditoController::class, 'listSolicitudCreditoById']); // Listar solicitudes por ID
    // Route::post('/updateSolicitudCredito/{id}', [solicitudCreditoController::class, 'updateSolicitudCredito']); // Editar
    // Route::delete('/deleteSolicitudCredito/{id}', [solicitudCreditoController::class, 'deleteSolicitudCredito']); // Elimina
});


//----------------------- END RUTAS JUAN  ----------------------------------------------
