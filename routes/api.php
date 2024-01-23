<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\crm\ActividadesFormulasController;
use App\Http\Controllers\crm\auditoria\ClienteAditoriaController;
use App\Http\Controllers\crm\BitacoraController;
use App\Http\Controllers\crm\CActividadClienteController;
use App\Http\Controllers\crm\CActividadController;
use App\Http\Controllers\crm\CasoController;
use App\Http\Controllers\crm\CFormularioController;
use App\Http\Controllers\crm\ClienteCrmController;
use App\Http\Controllers\crm\ClienteOpenceoController;
use App\Http\Controllers\crm\ComentariosController;
use App\Http\Controllers\crm\CondicionesController;
use App\Http\Controllers\crm\credito\ArchivoController;
use App\Http\Controllers\crm\credito\ClienteEnrolamientoController;
use App\Http\Controllers\crm\credito\EtiquetaController;
use App\Http\Controllers\crm\credito\GaleriaController;
use App\Http\Controllers\crm\credito\ParentescoController;
use App\Http\Controllers\crm\credito\RobotCasoController;
use App\Http\Controllers\crm\credito\solicitudCreditoController;
use App\Http\Controllers\crm\credito\TipoGaleriaController;
use App\Http\Controllers\crm\DashboardController;
use App\Http\Controllers\crm\EmailController;
use App\Http\Controllers\crm\TipoCasoFormulasController;
use App\Http\Controllers\crm\TipoTelefonoController;
use App\Http\Controllers\crm\CrmController;
use App\Http\Controllers\crm\CTareaController;
use App\Http\Controllers\crm\CTipoResultadoCierreController;
use App\Http\Controllers\crm\DActividadController;
use App\Http\Controllers\crm\DepartamentoController;
use App\Http\Controllers\crm\EntidadController;
use App\Http\Controllers\crm\EstadosController;
use App\Http\Controllers\crm\EstadosFormulasController;
use App\Http\Controllers\crm\FaseController;
use App\Http\Controllers\crm\FlujoController;
use App\Http\Controllers\crm\NotaController;
use App\Http\Controllers\crm\NotificacionesController;
use App\Http\Controllers\crm\PerfilAnalistasController;
use App\Http\Controllers\crm\ReferenciasClienteController;
use App\Http\Controllers\crm\ReqCasoController;
use App\Http\Controllers\crm\RequerimientoController;
use App\Http\Controllers\crm\RespuestasCasoController;
use App\Http\Controllers\crm\TableroController;
use App\Http\Controllers\crm\TareaController;
use App\Http\Controllers\crm\TipoCasoController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\JWTController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\openceo\ClienteDynamoController;
use App\Http\Controllers\openceo\PedidoMovilController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\user\EquifaxController;
use App\Http\Controllers\User\ProfileUserController;
use App\Http\Controllers\crm\garantias\PartesController;
use App\Http\Controllers\crm\garantias\ConfigItemsController;
use App\Http\Controllers\crm\garantias\RelacionLineasGexController;
use App\Http\Controllers\crm\garantias\ExepcionGexController;
use App\Http\Controllers\crm\garantias\RubrosReservaController;
use App\Http\Controllers\crm\garantias\GEXController;
use App\Http\Controllers\crm\series\PreIngresoController;
use App\Http\Controllers\crm\series\DespachoController;
use App\Http\Controllers\crm\series\InventarioController;
use App\Http\Controllers\crm\series\KardexSeriesController;
use App\Http\Controllers\crm\garantias\ContratosController;
use App\Http\Controllers\crm\garantias\RelacionUsuariosAlamcenGexController;
use App\Http\Controllers\crm\garantias\VentasTotalesGexController;
use App\Http\Controllers\crm\series\InventarioFechaSeriesController;
use App\Http\Controllers\crm\series\InformeInventarioSeriesController;
use App\Http\Controllers\crm\series\ComparativoSeriesController;
use App\Http\Controllers\crm\garantias\VentasProductosGexController;
use App\Http\Controllers\crm\TableroProcesosController;
use App\Http\Controllers\formulario\CampoController;
use App\Http\Controllers\formulario\FormController;
use App\Http\Controllers\formulario\FormSeccionController;
use Illuminate\Support\Facades\Route;

/*w
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
Route::group(["prefix" => "crm"], function ($router) {
    //CRM CONTROLLER PRINCIPAL
    Route::get('/crmTablero/{id}', [CrmController::class, 'list']);
    //notificaciones
    Route::post('/addNotificacion', [NotificacionesController::class, 'add']);

    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);


    Route::post('/actualizarTarea', [TareaController::class, 'actualizarTarea']);
    Route::post('/actualizarTareas', [TareaController::class, 'actualizarTareas']);
    Route::get('/buscarTarea/{id}', [TareaController::class, 'buscarTarea']);




    //------------------------------------------------------------------>FASE
    Route::post('/listFase', [FaseController::class, 'list']);
    Route::post('/addFase', [FaseController::class, 'add']);
    Route::put('/editFase', [FaseController::class, 'edit']);
    Route::get('/faseActualById/{faseId}', [FaseController::class, 'faseActualById']); //actualizarOrdenFases
    Route::post('/actualizarOrdenFases', [FaseController::class, 'actualizarOrdenFases']); //
    // Route::put('/update-flujos', [FlujoController::class, 'updateFlujos']);
    // Route::delete('/delete-flujo/{id}', [FlujoController::class, 'delete']);

    //------------------------------------------------------------------>CASO
    Route::put('/editCasoFase', [CasoController::class, 'editFase']);
    Route::post('/addCaso', [CasoController::class, 'add']);
    Route::put('/bloqueoCaso', [CasoController::class, 'bloqueoCaso']);
    Route::get('/casoById/{id}', [CasoController::class, 'casoById']);
    Route::put('/editCaUsAs', [CasoController::class, 'reasignarCaso']);
    Route::post('/respuestaCaso', [CasoController::class, 'respuestaCaso']);
    Route::get('/depUserTablero/{casoId}', [CasoController::class, 'depUserTablero']);
    Route::get('/addCasoOPMICreativa/{cppId}', [CasoController::class, 'addCasoOPMICreativa']);
    Route::put('/actualizarCaso/{casoId}', [CasoController::class, 'actualizarCaso']);//
    //---------------------------------------------------------------->PRUEBAS
    Route::get('/actualizarReqCaso/{entId}', [CasoController::class, 'validarClienteSolicitudCredito']); //
    /************************  FORMULARIOS   *********************** */
    Route::get('/listAllForm', [CFormularioController::class, 'listAll']); //
    Route::get('/getFormById/{id}', [CFormularioController::class, 'getFormById']); //
    /************************  REQUERIMIENTOS CASO   *********************** */
    Route::post('/addSolicitudCreditoReqCaso', [ReqCasoController::class, 'addSolicitudCreditoReqCaso']); // Guardar
    Route::get('/listAllReqCaso/{casoId}', [ReqCasoController::class, 'listAll']);
    Route::post('/editReqTipoFile', [ReqCasoController::class, 'editReqTipoFile']);
    Route::post('/editReqCaso', [ReqCasoController::class, 'edit']);
    Route::get('/listaReqCasoId/{casoId}', [ReqCasoController::class, 'listaReqCasoId']); //



    Route::post('/addTarea', [TareaController::class, 'add']);
    Route::get('/listTareas', [TareaController::class, 'list']);
    Route::get('/byCedula/{cedula}', [EntidadController::class, 'byCedula']); // Listar




    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [ComentariosController::class, 'guardarComentario']);

    Route::get('/listAnalistas/{tableroId}', [UserController::class, 'listAnalistas']);
    Route::get('/listUsuariosActivos', [UserController::class, 'listUsuariosActivos']);

    Route::get('/listCasosUsuarios/{tabId}', [TableroProcesosController::class, 'list']); //list

    /************************  OPENCEO   *********************** */

    Route::get('/clienteByCedula/{cedula}', [ClienteOpenceoController::class, 'byCedula']);
    Route::get('/listClientes/{parametro}', [ClienteOpenceoController::class, 'list']);
    Route::get('/clienteCasoList/{depId}', [ClienteOpenceoController::class, 'clienteCasoList']);
    Route::get('/solicitudByEntId/{entIdentificacion}/{userId}', [solicitudCreditoController::class, 'solicitudByEntId']);

    /************************  PEDIDO MOVIL OPENCEO   ****************** */
    Route::get('/getPedidoById/{cppId}', [PedidoMovilController::class, 'getPedidoById']);

    Route::get('/comprasCliente/{entId}', [DashboardController::class, 'comprasCliente']);

    Route::post('/addClienteOpenceo', [ClienteDynamoController::class, 'add']);



});
Route::group(["prefix" => "crm/audi"], function ($router) {
    Route::get('/cliTabAmortizacion/{cuentaanterior}', [ClienteAditoriaController::class, 'cliTabAmortizacion']);
});
Route::group(["prefix" => "crm/robot"], function ($router) {
    Route::post('/reasignarCaso', [RobotCasoController::class, 'reasignarCaso']);
});

Route::group([], function ($router) {
    Route::post('/token', [EquifaxController::class, 'loginEquifax']);
});

Route::group(["prefix" => "form"], function ($router) {
    Route::get('/list', [FormController::class, 'list']);
    Route::get('/storeA/{formId}', [FormController::class, 'storeA']);
    Route::get('/storeB/{formId}/{userId}', [FormController::class, 'storeB']);
    Route::get('/listByDepar/{depId}/{userId}', [FormController::class, 'listByDepar']); //
    Route::get('/formUser/{depId}/{userId}', [FormController::class, 'formUser']); //formUser
    Route::get('/byId/{formId}', [FormController::class, 'byId']); //formUser
    Route::get('/listAll', [FormController::class, 'listAll']); //
    Route::get('/listAnonimos', [FormController::class, 'listAnonimos']);
    Route::get('/impresion/{formId}/{userId}', [FormController::class, 'impresion']);//impresion
    Route::put('/edit/{id}', [FormController::class, 'edit']);
});
Route::group(['prefix' => 'form/campo'], function ($router) {
    Route::get('/store', [CampoController::class, 'store']);
    Route::get('/full/{id}', [CampoController::class, 'full']);
    Route::get('/list', [CampoController::class, 'list']);
    Route::get('/listAll', [CampoController::class, 'listAll']);
    Route::get('/byId/{id}', [CampoController::class, 'byId']);
    Route::get('/deleted', [CampoController::class, 'deleted']);
    Route::get('/restoreById/{id}', [CampoController::class, 'restoreById']);
    Route::put('/edit/{id}', [CampoController::class, 'edit']);
    Route::delete('/deleteById/{id}', [CampoController::class, 'deleteById']);
    Route::post('/add', [CampoController::class, 'add']); //addCampoValor
    Route::post('/addCampoValor', [CampoController::class, 'addCampoValor']);//addCampoValor
});
Route::group(['prefix' => 'form/seccion'], function ($router) {
    Route::get('/store', [FormSeccionController::class, 'store']);
    Route::post('/add', [FormSeccionController::class, 'add']);
    Route::put('/edit/{id}', [FormSeccionController::class, 'edit']);
});

//----------------------- FIN RUTAS FELIPE ----------------------------------------------
//----------------------- FIN RUTAS FELIPE ----------------------------------------------
//----------------------- FIN RUTAS FELIPE ----------------------------------------------



//----------------------- START RUTAS JUAN  ----------------------------------------------

Route::group(["prefix" => "crm"], function ($router) {

    // GALERIA

    Route::post('/addGaleria/{caso_id}', [GaleriaController::class, 'addGaleria']); // Guardar la imagen
    Route::get('/listGaleriaByCasoId/{id}', [GaleriaController::class, 'listGaleriaByCasoId']); // Listar las imagenes
    Route::post('/editGaleria/{id}', [GaleriaController::class, 'editGaleria']); // Edita la imagen
    Route::delete('/deleteGaleria/{id}', [GaleriaController::class, 'deleteGaleria']); // Elimina la imagen
    Route::get('/listGaleriaBySolicitudCreditoId/{id}', [GaleriaController::class, 'listGaleriaBySolicitudCreditoId']); // Listar las imagenes

    Route::get('/allTipoGaleria', [TipoGaleriaController::class, 'allTipoGaleria']); // Listar los tipos de imagenes

    // ARCHIVO

    Route::post('/addArchivo/{caso_id}', [ArchivoController::class, 'addArchivo']); // Guardar
    Route::post('/addArrayArchivos/{caso_id}', [ArchivoController::class, 'addArrayArchivos']); // Guardar
    Route::get('/listArchivoByCasoId/{id}', [ArchivoController::class, 'listArchivoByCasoId']); // Listar
    Route::post('/editArchivo/{id}', [ArchivoController::class, 'editArchivo']); // Editar
    Route::delete('/deleteArchivo/{id}', [ArchivoController::class, 'deleteArchivo']); // Eliminar
    //Para documentos de equifax
    Route::post('/addArchivosEquifax/{caso_id}', [ArchivoController::class, 'addArchivosEquifax']); // Guardar
    Route::get('/listArchivosSinFirmaEquifaxByCasoId/{caso_id}', [ArchivoController::class, 'listArchivosSinFirmaEquifaxByCasoId']); // Listar sin firmas
    Route::post('/editArchivosEquifax/{id}', [ArchivoController::class, 'editArchivosEquifax']); // Editar
    Route::get('/listArchivosEquifaxFirmadosByCasoId/{caso_id}', [ArchivoController::class, 'listArchivosEquifaxFirmadosByCasoId']); // Listar de firmados

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
    Route::post('/editTablero/{id}', [TableroController::class, 'editTablero']); // Editar
    Route::get('/listAllTableros', [TableroController::class, 'listAll']); // listar tablero mis casos
    Route::get('/listAllTablerosActivos', [TableroController::class, 'listAllTablerosActivos']); // listar tableros inactivos
    Route::get('/listAllTablerosInactivos', [TableroController::class, 'listAllTablerosInactivos']); // listar tableros inactivos
    Route::get('/listAllTablerosWithFases', [TableroController::class, 'listAllTablerosWithFases']); // listar tableros con sus fases
    Route::get('/listByTablerosIdWithFases/{tab_id}', [TableroController::class, 'listByTablerosIdWithFases']); // listar tableros con sus fases
    Route::get('/editMiembrosByTableroId/{id}', [TableroController::class, 'editMiembrosByTableroId']); // Editar los miembros del tablero
    Route::get('/usuariosTablero/{tabId}', [TableroController::class, 'usuariosTablero']); // usuariosTablero
    Route::get('/listTableroByDepId/{dep_id}', [TableroController::class, 'listTableroByDepId']); // listar

    // DEPARTAMENTO

    Route::get('/allDepartamento', [DepartamentoController::class, 'allDepartamento']); // listar
    Route::get('/listDepAllUser', [DepartamentoController::class, 'listAllUser']); // listar
    Route::get('/listDepartamento', [DepartamentoController::class, 'listDepartamento']); // listar
    Route::post('/addDepartamento', [DepartamentoController::class, 'addDepartamento']); // guardar
    Route::post('/editDepartamento/{id}', [DepartamentoController::class, 'editDepartamento']); // Editar
    Route::delete('/deleteDepartamento/{id}', [DepartamentoController::class, 'deleteDepartamento']); // Eliminar

    // NOTAS

    Route::post('/addNota', [NotaController::class, 'addNota']); // guardar
    Route::get('/listNotaByCasoId/{id}', [NotaController::class, 'listNotaByCasoId']); // listar
    Route::post('/updateNota/{id}', [NotaController::class, 'updateNota']); // Editar
    Route::delete('/deleteNota/{id}', [NotaController::class, 'deleteNota']); // Eliminar

    // CASO

    Route::get('/listCasoById/{id}', [CasoController::class, 'listCasoById']); // listar
    Route::post('/editPrioridadCaso/{id}', [CasoController::class, 'editPrioridadCaso']);
    Route::post('/editarTipoCaso/{id}', [CasoController::class, 'editarTipoCaso']);
    Route::post('/editObservacion/{id}', [CasoController::class, 'editObservacion']); // Editar la observación del caso
    Route::get('/listHistoricoEstadoCaso/{caso_id}', [CasoController::class, 'listHistoricoEstadoCaso']); // listado/ historico de los estados del caso
    Route::get('/listHistorialCaso/{caso_id}', [CasoController::class, 'listHistorialCaso']); // listado/ historico de los estados del caso

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
    // Route::get('listActividadesByIdCasoId/{caso_id}/{user_id}', [DActividadController::class, 'listActividadesByIdCasoId']); // listar activiades por user_id
    Route::get('listActividadesByDepIdCasoId/{caso_id}/{dep_id}', [DActividadController::class, 'listActividadesByDepIdCasoId']); // listar actividades por departamento USUARIO COMUN
    Route::get('listAllActividadesByCasoId/{caso_id}', [DActividadController::class, 'listAllActividadesByCasoId']); // listar ALL actividades SUPER USUARIO
    Route::post('/updateDActividad/{id}', [DActividadController::class, 'updateDActividad']); // Edita la actividad
    Route::post('/editAccesoActividad/{id}', [DActividadController::class, 'editAccesoActividad']); // Edita el acceso publico de la actividad
    Route::get('listActividadesByUserId/{user_id}', [DActividadController::class, 'listActividadesByUserId']); // listar TABLA DE MIS ACTIVIDADES
    // Route::delete('/deleteCTipoActividad/{id}', [DActividadController::class, 'deleteCTipoActividad']); // Eliminar
    Route::post('/addDTipoActividadTabla/{user_id}', [DActividadController::class, 'addDTipoActividadTabla']); // guardar
    Route::post('/updateDActividadTabla/{id}/{user_id}', [DActividadController::class, 'updateDActividadTabla']); // Edita la actividad
    Route::get('listActividadesIniciadasByUserId/{user_id}', [DActividadController::class, 'listActividadesIniciadasByUserId']); // listar para el calendario

    // CTIPORESULTADOCIERRE

    Route::post('/addCTipoResultadoCierre', [CTipoResultadoCierreController::class, 'addCTipoResultadoCierre']); // guardar
    Route::get('listCTipoResultadoCierreByIdTablero/{tab_id}', [CTipoResultadoCierreController::class, 'listCTipoResultadoCierreByIdTablero']); // listar
    Route::get('listCTipoResultadoCierreByIdTableroEstadoActivo/{tab_id}', [CTipoResultadoCierreController::class, 'listCTipoResultadoCierreByIdTableroEstadoActivo']); // listar
    Route::get('listCTipoResultadoCierreByIdCasoId/{caso_id}', [CTipoResultadoCierreController::class, 'listCTipoResultadoCierreByIdCasoId']); // listar
    Route::post('/editCTipoResultadoCierre/{id}', [CTipoResultadoCierreController::class, 'editCTipoResultadoCierre']); // Edita la actividad
    Route::delete('/deleteCTipoResultadoCierre/{id}', [CTipoResultadoCierreController::class, 'deleteCTipoResultadoCierre']); // Eliminar
    Route::get('listResultadoIniciadoByTableroId/{tab_id}', [CTipoResultadoCierreController::class, 'listResultadoIniciadoByTableroId']); // listar

    // CHAT GRUPAL

    // Route::post('/addChatGrupal', [ChatController::class, 'addChatGrupal']); // guardar
    // Route::get('/listChatByCasoId/{caso_id}', [ChatController::class, 'listChatByCasoId']); // by casi_id
    // Route::post('/editChatGrupal/{id}', [ChatController::class, 'editChatGrupal']); // Editar

    // TIPO CASO

    Route::post('/addTipoCaso', [TipoCasoController::class, 'addTipoCaso']); // guardar
    Route::get('listTipoCasoByIdTablero/{tab_id}', [TipoCasoController::class, 'listTipoCasoByIdTablero']); // listar
    Route::get('listTipoCasoByIdTableroEstadoActivo/{tab_id}', [TipoCasoController::class, 'listTipoCasoByIdTableroEstadoActivo']); // listar
    Route::get('listTipoCasoByIdCasoId/{caso_id}', [TipoCasoController::class, 'listTipoCasoByIdCasoId']); // listar
    Route::get('listByIdTipoCasoActivo/{tc_id}', [TipoCasoController::class, 'listByIdTipoCasoActivo']); // listar
    Route::post('/editTipoCaso/{id}', [TipoCasoController::class, 'editTipoCaso']); // Edita la actividad
    Route::delete('/deleteTipoCaso/{id}', [TipoCasoController::class, 'deleteTipoCaso']); // Eliminar

    // TAREAS INDIVIDUALES

    Route::get('listTareasCasoById/{caso_id}/{tab_id}', [TareaController::class, 'listTareasCasoById']); // by caso_id
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
    Route::get('/listUsuariosByTableroId/{tablero_id}', [UserController::class, 'listUsuariosByTableroId']); // listar usuarios del tablero
    Route::get('/listUsuarioById/{user_id}', [UserController::class, 'listUsuarioById']); // listar usuario por ID

    Route::get('/listAlmacenes', [UserController::class, 'listAlmacenes']); // listar almacenes

    Route::post('/editEnLineaUser/{user_id}', [UserController::class, 'editEnLineaUser']); // editar en linea del usuario

    // NOTIFICACIONES

    Route::get('/allByDepartamento/{id}', [NotificacionesController::class, 'allByDepartamento']);
    Route::get('/listByDepartamento/{id}', [NotificacionesController::class, 'listByDepartamento']);
    Route::post('/editLeidoNotificacion/{id}', [NotificacionesController::class, 'editLeidoNotificacion']);
    Route::post('/editLeidoAllNotificaciones/{id}', [NotificacionesController::class, 'editLeidoAllNotificaciones']);

    // REQUERIMIENTOS

    Route::get('listRequerimientosByFaseId/{fase_id}', [RequerimientoController::class, 'listRequerimientosByFaseId']); // by caso_id
    Route::post('/addRequerimientos', [RequerimientoController::class, 'addRequerimientos']); // guardar
    Route::post('/editRequerimientos/{id}', [RequerimientoController::class, 'editRequerimientos']); // Editar
    Route::delete('/deleteRequerimientos/{id}', [RequerimientoController::class, 'deleteRequerimientos']); // Eliminar


    // CActividadCliente

    Route::post('/addCActividadCliente', [CActividadClienteController::class, 'addCActividadCliente']); // guardar
    Route::get('/listCActividadClienteByIdTablero/{tab_id}', [CActividadClienteController::class, 'listCActividadClienteByIdTablero']); // listar
    Route::post('/editCActividadCliente/{id}', [CActividadClienteController::class, 'editCActividadCliente']); // Editar
    Route::get('/listCACReqCaso', [CActividadClienteController::class, 'listCACReqCaso']); //
    // Estados

    Route::get('/listEstadosByTablero/{tab_id}', [EstadosController::class, 'listEstadosByTablero']); // listar
    Route::get('/listEstadosActivoByTablero/{tab_id}', [EstadosController::class, 'listEstadosActivoByTablero']); // listar
    Route::post('/addEstado', [EstadosController::class, 'addEstado']); // guardar
    Route::post('/editEstado/{id}', [EstadosController::class, 'editEstado']); // Editar
    Route::delete('/deleteEstado/{id}', [EstadosController::class, 'deleteEstado']); // Eliminar

    // RespuestasCaso

    Route::get('/listRespuestasCasoByTablero/{tab_id}', [RespuestasCasoController::class, 'listRespuestasCasoByTablero']); // listar
    Route::get('/listRespuestasCasoActivoByTablero/{tab_id}', [RespuestasCasoController::class, 'listRespuestasCasoActivoByTablero']); // listar
    Route::post('/addRespuestasCaso', [RespuestasCasoController::class, 'addRespuestasCaso']); // guardar
    Route::post('/editRespuestasCaso/{id}', [RespuestasCasoController::class, 'editRespuestasCaso']); // Editar
    Route::delete('/deleteRespuestasCaso/{id}', [RespuestasCasoController::class, 'deleteRespuestasCaso']); // Eliminar

    // EstadosFormulas

    Route::get('/listEstadosFormulasByTablero/{tab_id}', [EstadosFormulasController::class, 'listEstadosFormulasByTablero']); // listar
    Route::post('/addEstadosFormulas', [EstadosFormulasController::class, 'addEstadosFormulas']); // guardar
    Route::post('/editEstadosFormulas/{id}', [EstadosFormulasController::class, 'editEstadosFormulas']); // Editar
    Route::delete('/deleteEstadosFormulas/{id}', [EstadosFormulasController::class, 'deleteEstadosFormulas']); // Eliminar

    // ActividadesFormulas

    Route::get('/listActividadesFormulasByTablero/{tab_id}', [ActividadesFormulasController::class, 'listActividadesFormulasByTablero']); // listar
    Route::post('/addActividadesFormulas', [ActividadesFormulasController::class, 'addActividadesFormulas']); // guardar
    Route::post('/editActividadesFormulas/{id}', [ActividadesFormulasController::class, 'editActividadesFormulas']); // Editar
    Route::delete('/deleteActividadesFormulas/{id}', [ActividadesFormulasController::class, 'deleteActividadesFormulas']); // Eliminar
    Route::get('/listActividadFormulaById/{result_id_actual}/{result_id}', [ActividadesFormulasController::class, 'listActividadFormulaById']); // listar

    // Perfil Analistas

    Route::get('/listAllPerfilAnalistas', [PerfilAnalistasController::class, 'listAllPerfilAnalistas']); // guardar
    Route::post('/addPerfilAnalistas', [PerfilAnalistasController::class, 'addPerfilAnalistas']); // guardar
    Route::post('/editPerfilAnalistas/{id}', [PerfilAnalistasController::class, 'editPerfilAnalistas']); // Editar
    Route::delete('/deletePerfilAnalistas/{id}', [PerfilAnalistasController::class, 'deletePerfilAnalistas']); // Eliminar

    // CONTROL TIEMPOS CASO

    Route::post('/editCalcularTiemposCaso/{caso_id}', [CasoController::class, 'editCalcularTiemposCaso']); // Editar

    // TIPO CASO FORMULAS

    Route::get('/listTpoCasoFormulasById/{tab_id}/{tc_id}', [TipoCasoFormulasController::class, 'listTpoCasoFormulasById']); // listar por la llave tab_id y tc_id
    Route::get('/listTpoCasoFormulas', [TipoCasoFormulasController::class, 'listTpoCasoFormulas']); // listar all
    Route::get('/listTpoCasoFormulasActivos', [TipoCasoFormulasController::class, 'listTpoCasoFormulasActivos']); // listar activos
    Route::post('/addTipoCasoFormulas', [TipoCasoFormulasController::class, 'addTipoCasoFormulas']); // guardar
    Route::post('/editTipoCasoFormulas/{id}', [TipoCasoFormulasController::class, 'editTipoCasoFormulas']); // editar
    Route::delete('/deleteTipoCasoFormulas/{id}', [TipoCasoFormulasController::class, 'deleteTipoCasoFormulas']); // eliminar

});

Route::group([], function ($router) {
    Route::post('/getDocuments/{caso_id}', [EquifaxController::class, 'getDocuments']);
});


Route::group(["prefix" => "credito"], function ($router) {

    // SOLICITUD CREDITO
    Route::post('/editSolicitudCredito/{id}', [solicitudCreditoController::class, 'editSolicitudCredito']); // Editar
    Route::get('/listSolicitudCreditoByEntidadId/{id}', [solicitudCreditoController::class, 'listSolicitudCreditoByEntidadId']); // Listar por entidad ID
    Route::get('/listSolicitudCreditoByRucCedula/{cedula}', [solicitudCreditoController::class, 'listSolicitudCreditoByRucCedula']); // Listar por cedula
    Route::get('/solicitudByIdentificacion/{cedula}/{id_user_creador}', [solicitudCreditoController::class, 'solicitudByIdentificacion2']); // Listar por cedula
    Route::get('/listSolicitudCreditoByClienteId/{cliente_id}', [solicitudCreditoController::class, 'listSolicitudCreditoByClienteId']); // Listar por cedula

    // EQUIFAX / CLIENTE ENROLAMIENTO

    Route::post('/addClienteEnrolamiento', [ClienteEnrolamientoController::class, 'addClienteEnrolamiento']); // Guardar la imagen de equifax
    Route::get('/clienteEnroladoById/{id}', [ClienteEnrolamientoController::class, 'clienteEnroladoById']); // listar datos cliente enrolado por caso_id
    Route::post('/validarReqCasoCliente', [ClienteEnrolamientoController::class, 'validarReqCasoCliente']); // Guardar la imagen de equifax addClienteEnrolByCliente
    Route::post('/addArchivosFirmadosEnrolamiento', [ClienteEnrolamientoController::class, 'addArchivosFirmadosEnrolamiento']); // guardar los archivos firmados
    Route::get('/listEnrolamientosById/{cli_id}/{caso_id}', [ClienteEnrolamientoController::class, 'listEnrolamientosById']); // lista todos los enrolamientos del cliente

    // CLIENTE CRM

    Route::get('/listClienteCrmById/{id}', [ClienteCrmController::class, 'listClienteCrmById']); // lista
    Route::post('/addClienteCrm', [ClienteCrmController::class, 'addClienteCrm']); // Guardar
    Route::post('/editClienteCrm/{ent_id}', [ClienteCrmController::class, 'editClienteCrm']); // Editar

    // Referencias CRM

    Route::post('/addReferenciasCliente', [ReferenciasClienteController::class, 'addReferenciasCliente']); // Guardar
    Route::post('/editReferenciasCliente/{id}', [ReferenciasClienteController::class, 'editReferenciasCliente']); // Editar
    Route::delete('/deleteReferenciasCliente/{id}', [ReferenciasClienteController::class, 'deleteReferenciasCliente']); // Eliminar
    Route::get('/listReferenciasByClienteId/{cli_id}', [ReferenciasClienteController::class, 'listReferenciasByClienteId']); // lista

    // parentesco CRM

    Route::get('/listParentesco', [ParentescoController::class, 'listParentesco']); // listar

    // Tipo Telefono CRM

    Route::get('/listTipoTelefono', [TipoTelefonoController::class, 'listTipoTelefono']); // listar

    // Email - correo electronico

    Route::post('/send_emailCambioFase/{caso_id}/{fase_id}', [EmailController::class, 'send_emailCambioFase']); // Envia un correo cuando cambia de fase
    Route::post('/send_emailLinkEnrolamiento', [EmailController::class, 'send_emailLinkEnrolamiento']); // Envia un correo con el link del enrolamiento

    Route::get('/listEmailByFaseId/{fase_id}', [EmailController::class, 'listEmailByFaseId']); // lista el correo de la fase
    Route::post('/addEmail', [EmailController::class, 'addEmail']);
    Route::post('/editEmail/{id}', [EmailController::class, 'editEmail']);

});


//----------------------- END RUTAS JUAN  ----------------------------------------------








//----------------------- START RUTAS JAIRO  ----------------------------------------------
Route::group(["prefix" => "crm"], function ($router) {
    //Partes
    Route::get('/listado', [PartesController::class, 'listado']);
    Route::get('/byParte/{parte}', [PartesController::class, 'byParte']);
    Route::post('/grabaParte', [PartesController::class, 'grabaParte']);
    Route::get('/eliminaParte/{parte}', [PartesController::class, 'eliminaParte']);

    //Configuracion Items
    Route::get('/listadoConfig', [ConfigItemsController::class, 'listado']);
    Route::get('/listadoProductos', [ConfigItemsController::class, 'productos']);
    Route::get('/listadoPartes', [ConfigItemsController::class, 'partes']);
    Route::post('/grabaConfig', [ConfigItemsController::class, 'grabaConfig']);
    Route::get('/byConfig/{producto}', [ConfigItemsController::class, 'byConfig']);
    Route::get('/eliminaConfig/{producto}', [ConfigItemsController::class, 'eliminaConfig']);
    Route::post('/validaConfig', [ConfigItemsController::class, 'validaConfig']);

    //Relacion Lineas Gex
    Route::get('/listadoRelacion', [RelacionLineasGexController::class, 'listado']);
    Route::get('/listadoProductosGex', [RelacionLineasGexController::class, 'productos']);
    Route::get('/listadoLineas', [RelacionLineasGexController::class, 'lineas']);
    Route::post('/grabaRela', [RelacionLineasGexController::class, 'grabaRela']);
    Route::get('/byRela/{linea}/{producto}', [RelacionLineasGexController::class, 'byRela']);
    Route::get('/eliminaRela/{linea}/{producto}', [RelacionLineasGexController::class, 'eliminaRela']);

    //Excepción Gex
    Route::get('/listadoExepcion', [ExepcionGexController::class, 'listado']);
    Route::get('/listadoProductosExcep', [ExepcionGexController::class, 'productos']);
    Route::post('/grabaExep', [ExepcionGexController::class, 'grabaExep']);
    Route::get('/byExcep/{excep}', [ExepcionGexController::class, 'byExcep']);
    Route::get('/eliminaExep/{excep}', [ExepcionGexController::class, 'eliminaExep']);
    Route::get('/gexRelacionado/{tipo_producto}', [ExepcionGexController::class, 'gexRelacionado']);
    Route::post('/validaExcep', [ExepcionGexController::class, 'validaExcep']);

    //Rubro de Reserva
    Route::get('/listadoRubros', [RubrosReservaController::class, 'listado']);
    Route::post('/grabaRubro', [RubrosReservaController::class, 'grabaRubro']);
    Route::get('/byRubro/{rubro}', [RubrosReservaController::class, 'byRubro']);
    Route::get('/eliminaRubro/{rubro}', [RubrosReservaController::class, 'eliminaRubro']);

    //Preingreso de Series
    Route::get('/listadoPreIngreso', [PreIngresoController::class, 'listado']);
    Route::get('/listadoProdPI', [PreIngresoController::class, 'productos']);
    Route::get('/listadoBodegas', [PreIngresoController::class, 'bodegas']);
    Route::get('/listadoClientes', [PreIngresoController::class, 'clientes']);
    Route::post('/grabaPreIngreso', [PreIngresoController::class, 'grabaPreIngreso']);
    Route::get('/byPreIngreso/{numero}', [PreIngresoController::class, 'byPreIngreso']);
    Route::get('/anulaPreIngreso/{numero}', [PreIngresoController::class, 'anulaPreIngreso']);
    Route::get('/eliminaPreIngreso/{numero}', [PreIngresoController::class, 'eliminaPreIngreso']);
    Route::get('/cargaIngresos', [PreIngresoController::class, 'cargaIngresos']);
    Route::get('/cargaDetalleIngreso/{id}/{tipo}', [PreIngresoController::class, 'cargaDetalleIngreso']);
    Route::get('/cargaPreingresos', [PreIngresoController::class, 'cargaPreingresos']);
    Route::get('/cargaRelaciones', [PreIngresoController::class, 'cargaRelaciones']);
    Route::post('/relacionaPreIngreso', [PreIngresoController::class, 'relacionaPreIngreso']);
    Route::get('/quitaRelacionPI/{numero}/{usuario}', [PreIngresoController::class, 'quitaRelacionPI']);
    Route::get('/validaSeriePreIngreso/{producto}/{serie}/{tipo}', [PreIngresoController::class, 'validaSerie']);

    //Despacho de Series
    Route::get('/listadoDocumentosDes/{bodega}', [DespachoController::class, 'listado']);
    Route::get('/bodegaUsuario/{usuario}', [DespachoController::class, 'bodegaUsuario']);
    Route::get('/listadoProdDes/{id}/{tipo}', [DespachoController::class, 'productos']);
    Route::get('/listadoBodegasDes', [DespachoController::class, 'bodegas']);
    Route::get('/listadoClientesDes/{tipo}/{id}', [DespachoController::class, 'clientes']);
    Route::post('/grabaDespacho', [DespachoController::class, 'grabaDespacho']);
    Route::get('/imprimeDespacho/{numero}', [DespachoController::class, 'imprimeDespacho']);
    Route::get('/listadoDespachos', [DespachoController::class, 'listadoDespachos']);
    Route::get('/byDespacho/{numero}', [DespachoController::class, 'byDespacho']);
    Route::get('/anulaDespacho/{numero}/{bodDest}', [DespachoController::class, 'anulaDespacho']);
    Route::get('/eliminaDespacho/{numero}/{bodDest}', [DespachoController::class, 'eliminaDespacho']);
    Route::get('/validaSerie/{producto}/{serie}/{bodega}/{tipo}', [DespachoController::class, 'validaSerie']);

    //Inventario de Series
    Route::get('/listadoProdInv', [InventarioController::class, 'productos']);
    Route::get('/listadoBodegasInv', [InventarioController::class, 'bodegas']);
    Route::post('/grabaInventario', [InventarioController::class, 'grabaInventario']);
    Route::get('/imprimeInventario/{numero}', [InventarioController::class, 'imprimeInventario']);
    Route::get('/listadoInventarios', [InventarioController::class, 'listado']);
    Route::get('/byInventario/{numero}', [InventarioController::class, 'byInventario']);
    Route::get('/byInventarioProc/{numero}', [InventarioController::class, 'byInventarioProc']);
    Route::get('/anulaInventario/{numero}', [InventarioController::class, 'anulaInventario']);
    Route::get('/eliminaInventario/{numero}', [InventarioController::class, 'eliminaInventario']);

    //Kardex de Series
    Route::get('/listadoProdKar', [KardexSeriesController::class, 'productos']);
    Route::get('/listadoBodegasKar', [KardexSeriesController::class, 'bodegas']);
    Route::get('/kardexSeries/{fecIni}/{fecFin}/{bodega}/{producto}/{tipo}/{serie}', [KardexSeriesController::class, 'kardexSeries']);

    //Contratos GEX
    Route::get('/contratosGex', [ContratosController::class, 'listado']);
    Route::get('/almacenes', [ContratosController::class, 'almacenes']);
    Route::get('/facturas/{almacen}', [ContratosController::class, 'facturas']);
    Route::get('/datosContrato/{factura}', [ContratosController::class, 'datosContrato']);
    Route::get('/byContrato/{almacen}/{numero}', [ContratosController::class, 'byContrato']);
    Route::post('/grabaContrato', [ContratosController::class, 'grabaContrato']);
    Route::get('/eliminaContrato/{almacen}/{numero}', [ContratosController::class, 'eliminaContrato']);

    //Relacion Usuarios Almacen Gex
    Route::get('/listadoRelaUsuAlma', [RelacionUsuariosAlamcenGexController::class, 'listado']);
    Route::get('/listadoUsuariosRela', [RelacionUsuariosAlamcenGexController::class, 'usuarios']);
    Route::get('/listadoAlmacenesRela', [RelacionUsuariosAlamcenGexController::class, 'almacenes']);
    Route::post('/grabaRelaUsuAlma', [RelacionUsuariosAlamcenGexController::class, 'grabaRela']);
    Route::get('/byRelaUsuAlma/{usuario}/{almacen}', [RelacionUsuariosAlamcenGexController::class, 'byRela']);
    Route::get('/eliminaRelaUsuAlma/{usuario}/{almacen}', [RelacionUsuariosAlamcenGexController::class, 'eliminaRela']);

    //Ventas Totales Gex
    Route::get('/listadoAlamaUsu/{usuario}', [VentasTotalesGexController::class, 'almacenes']);
    Route::get('/listadoVendedores', [VentasTotalesGexController::class, 'vendedores']);
    Route::get('/ventasTotalesGex/{almacen}/{usuario}/{vendedor}/{fecIni}/{fecFin}', [VentasTotalesGexController::class, 'VentasTotalesGex']);
    Route::get('/ventasTotalesGexAlmacen/{almacen}/{usuario}/{fecIni}/{fecFin}', [VentasTotalesGexController::class, 'VentasTotalesGexAlmacen']);

    //Inventario a la Fecha de Series
    Route::get('/listadoProdInvFec', [InventarioFechaSeriesController::class, 'productos']);
    Route::get('/listadoBodegasInvFec', [InventarioFechaSeriesController::class, 'bodegas']);
    Route::get('/invFecSeries/{fecCorte}/{bodega}/{producto}', [InventarioFechaSeriesController::class, 'invFecSeries']);

    //Informe de Inventario de Series
    Route::get('/listadoInv/{bodega}', [InformeInventarioSeriesController::class, 'inventarios']);
    Route::get('/listadoBodegasInfInv', [InformeInventarioSeriesController::class, 'bodegas']);
    Route::get('/infInvSeries/{bodega}/{numero}', [InformeInventarioSeriesController::class, 'infInvSeries']);

    //Comparativo de Series
    Route::get('/listadoInvCompa/{bodega}', [ComparativoSeriesController::class, 'inventarios']);
    Route::get('/listadoBodegasCompa', [ComparativoSeriesController::class, 'bodegas']);
    Route::get('/comparativoSeries/{fecCorte}/{bodega}/{numero}', [ComparativoSeriesController::class, 'comparativoSeries']);

    //Ventas Productos
    Route::get('/listadoLineasVentas', [VentasProductosGexController::class, 'lineas']);
    Route::get('/listadoLineasMotosVentas', [VentasProductosGexController::class, 'lineasMotos']);
    Route::get('/listadoProductosVentas/{tipoProd}', [VentasProductosGexController::class, 'productos']);
    Route::get('/listadaoAlmacenesVentas', [VentasProductosGexController::class, 'almacenes']);
    Route::get('/listadoFormasPago', [VentasProductosGexController::class, 'formasPago']);
    Route::get('/listadoVendedoresVentas', [VentasProductosGexController::class, 'vendedores']);
    Route::get('/VentasProductosGex/{tipoProd}/{producto}/{sucursal}/{formaPago}/{vendedor}/{fecIni}/{fecFin}', [VentasProductosGexController::class, 'VentasProductosGex']);
    Route::get('/VentasMotosGex/{tipoProd}/{producto}/{sucursal}/{formaPago}/{vendedor}/{fecIni}/{fecFin}', [VentasProductosGexController::class, 'VentasMotosGex']);

    //API GEX
    Route::post('/facturaGex', [GEXController::class, 'facturaGex']);
    Route::post('/devuelveGex', [GEXController::class, 'devuelveGex']);
});
//----------------------- END RUTAS JAIRO  ----------------------------------------------














Route::group([
    'prefix' => 'profile',
], function () {
    Route::get('all', [ProfileController::class, 'all']);
    Route::get('list', [ProfileController::class, 'list']);
    Route::get('list/{id}', [ProfileController::class, 'findById']);
    Route::post('create', [ProfileController::class, 'create']);
    Route::post('edit/{id}', [ProfileController::class, 'edit']);
    Route::delete('deleteProfile/{id}', [ProfileController::class, 'deleteProfile']);
    Route::post('clonProfile', [ProfileController::class, 'clonProfile']);
});


Route::group([
    'prefix' => 'access',
], function () {
    Route::get('program/{profile}/{program}', [ProfileController::class, 'findByProgram']);
    Route::get('menu/{userid}', [ProfileController::class, 'findByUser']);
});
Route::group([
    'prefix' => 'company',
], function () {
    Route::get('lista/{id}', [CompanyController::class, 'findById']);
    Route::put('editar/{id}', [CompanyController::class, 'edit']);
});

Route::group([
    'prefix' => 'menu',
], function () {

    Route::get('list', [MenuController::class, 'list']);
    Route::get('list/{id}', [MenuController::class, 'findById']);

    Route::post('addMenu', [MenuController::class, 'addMenu']);
    Route::post('editMenu/{id}', [MenuController::class, 'editMenu']);
    Route::delete('deleteMenu/{id}', [MenuController::class, 'deleteMenu']);
});











