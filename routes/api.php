<?php

use App\Http\Controllers\activos\ActasController;
use App\Http\Controllers\activos\ActivosController;
use App\Http\Controllers\api\ApiController;
use App\Http\Controllers\pinpad\PinpadController;
use App\Http\Controllers\pinpadjar\PinpadJarController;
use App\Http\Controllers\appreact\ReactNativeMaps;
use App\Http\Controllers\chat\ChatArchivosController;
use App\Http\Controllers\chat\ChatController;
use App\Http\Controllers\cobranzas\CobranzasController;
use App\Http\Controllers\comercializacion\RenegociacionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TableauAuthController;
use App\Http\Controllers\PowerBiRefreshDatasetController;
use App\Http\Controllers\GoogleSheetsController;
use App\Http\Controllers\configuracion\AgenciaController;
use App\Http\Controllers\configuracion\Archivos2Controller;
use App\Http\Controllers\anuncios\AnunciosController;
use App\Http\Controllers\anuncios\AnunciosUsuarioController;
use App\Http\Controllers\configuracion\CodigoQrController;
use App\Http\Controllers\configuracion\HorarioController;
use App\Http\Controllers\crm\ActividadesFormulasController;
use App\Http\Controllers\crm\AlmacenController;
use App\Http\Controllers\crm\auditoria\ClienteAditoriaController;
use App\Http\Controllers\crm\BitacoraController;
use App\Http\Controllers\crm\CActividadClienteController;
use App\Http\Controllers\crm\CActividadController;
use App\Http\Controllers\crm\CasoController;
use App\Http\Controllers\crm\CFormularioController;
use App\Http\Controllers\crm\ClienteCrmController;
use App\Http\Controllers\crm\ClienteOpenceoController;
use App\Http\Controllers\crm\ComentariosController;
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
use App\Http\Controllers\crm\EmailDinamicoController;
use App\Http\Controllers\crm\ParametroController;
use App\Http\Controllers\crm\seriesalm\SeriesAlm2Controller;
use App\Http\Controllers\crm\seriesalm\SeriesAlmController;
use App\Http\Controllers\crm\SeriesGeneradasController;
use App\Http\Controllers\crm\Tareas2Controller;
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
use App\Http\Controllers\crm\TutorialController;
use App\Http\Controllers\crm\TutorialUsuarioController;
use App\Http\Controllers\crm\UltimoIdController;
use App\Http\Controllers\fileManager\FmArchivoController;
use App\Http\Controllers\fileManager\FmAuditController;
use App\Http\Controllers\fileManager\FmBulkController;
use App\Http\Controllers\fileManager\FmCarpetaController;
use App\Http\Controllers\fileManager\FmPermisosController;
use App\Http\Controllers\fileManager\FmSearchController;
use App\Http\Controllers\fileManager\FmTagController;
use App\Http\Controllers\directorio\DirectorioController;
use App\Http\Controllers\formularios2\Formulario2Controller;
use App\Http\Controllers\formularios2\Formulario2UsuariosController;
use App\Http\Controllers\gestiones\GestionController;
use App\Http\Controllers\MigracionNovasoft\MigracionController;
use App\Http\Controllers\openceo\BodegaController;
use App\Http\Controllers\openceo\ClienteController as Cliente2Controller;
use App\Http\Controllers\openceo\EntidadDynamoController;
use App\Http\Controllers\reportes\ReporteLinkController;
use App\Http\Controllers\reportes\ReporteLinkUsuariosController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\JWTController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\openceo\ClienteDynamoController;
use App\Http\Controllers\openceo\PedidoMovilController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\EquifaxController;
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
use App\Http\Controllers\crm\garantias\RelacionAlamcenVendedorGexController;
use App\Http\Controllers\crm\garantias\MetasController;
use App\Http\Controllers\crm\garantias\MetasRecalcController;
use App\Http\Controllers\crm\garantias\ProcMetasController;
use App\Http\Controllers\crm\garantias\ComisionesController;
use App\Http\Controllers\crm\garantias\ProcComiController;
use App\Http\Controllers\crm\seriesalm\ContratoGexController;
use App\Http\Controllers\crm\seriesalm\StockProSerieController;
use App\Http\Controllers\crm\TableroProcesosController;
use App\Http\Controllers\formulario\CampoController;
use App\Http\Controllers\formulario\ComponentsController;
use App\Http\Controllers\formulario\FormAuthDatosCliController;
use App\Http\Controllers\formulario\FormController;
use App\Http\Controllers\formulario\FormSeccionController;
use App\Http\Controllers\openceo\DdocumentoController;
use App\Http\Controllers\openceo\DocumentacionClienteController;
use App\Http\Controllers\openceo\RenegociacionesController;
use App\Http\Controllers\corredores\CorredorClienteController;
use App\Http\Controllers\varios\ConsultaIdentidadExternoController;
use App\Http\Controllers\ParametrosController;
use App\Http\Controllers\User\UsuarioAlmacenController;
use App\Http\Controllers\configuracion\NotesController;
use App\Http\Controllers\configuracion\ZonaAgenciaController;
use App\Http\Controllers\correo\CorreoController;
use App\Http\Controllers\crediGestion\CrediGestionController;
use App\Http\Controllers\crm\cambioAgencia\CambioAgenciaController;
use App\Http\Controllers\crm\credito\ResumenController;
use App\Http\Controllers\crm\FaseController2;
use App\Http\Controllers\crm\seriesalm\BodegaSeriesGeneradasController;
use App\Http\Controllers\db_oracle\CelProspectoController;
use App\Http\Controllers\db_oracle\MultiNivelController;
use App\Http\Controllers\gestionClientes\ActividadClienteController;
use App\Http\Controllers\gestionClientes\AdjuntoClienteController;
use App\Http\Controllers\gestionClientes\BitacoraController as GestionClientesBitacoraController;
use App\Http\Controllers\gestionClientes\ClienteController;
use App\Http\Controllers\gestionClientes\CargoController;
use App\Http\Controllers\gestionClientes\ContactoController;
use App\Http\Controllers\gestionClientes\EstadoActividadController;
use App\Http\Controllers\gestionClientes\EtiquetaActividadController;
use App\Http\Controllers\gestionClientes\PrioridadActividadController;
use App\Http\Controllers\gestionClientes\TipoActividadController;
use App\Http\Controllers\gestionClientes\TipoAdjuntoController;
use App\Http\Controllers\gestionClientes\CSemaforoController;
use App\Http\Controllers\gestionClientes\DSemaforoController;
use App\Http\Controllers\gestionClientes\ZonaController;
use App\Http\Controllers\openceo\PoliticaController;
use App\Http\Controllers\ServidorController;
use Illuminate\Support\Facades\Route;


Route::group(['middleware' => 'api'], function ($router) {
    Route::post('/register', [JWTController::class, 'register']);
    Route::post('/login', [JWTController::class, 'login']);
    Route::post('/profile', [JWTController::class, 'profile']);
});

Route::group(['middleware' => 'api', 'prefix' => 'users'], function ($router) {
    Route::post('/profile-user', [ProfileUserController::class, 'profile_user']);
    Route::put('/profile', [ProfileUserController::class, 'profile_user']);
});

//app react native--------------------------------
Route::group(['middleware' => 'api', "prefix" => "reactapp"], function ($router) {
    Route::get('/getCoordenadasAlm/{almId}', [ReactNativeMaps::class, 'getCoordenadasAlm']);
});

Route::group([], function ($router) {
    Route::post('/token', [EquifaxController::class, 'loginEquifax']);
});

Route::group([], function ($router) {
    Route::post('/getDocuments/{caso_id}', [EquifaxController::class, 'getDocuments']);
});





// --------------- START RUTAS SIN TOKEN -----------
// --------------- START RUTAS SIN TOKEN -----------
// --------------- START RUTAS SIN TOKEN -----------

Route::group(["prefix" => "crm"], function ($router) {
    // DIRECTORIO
    Route::get('/listDirectorioCrm', [DirectorioController::class, 'listDirectorioCrm']);

    // FORMULARIOS EXTERNOS
    Route::get('/listFormulariosExternosPublicos', [TipoCasoFormulasController::class, 'listFormulariosExternosPublicos']);
    Route::get('/listFormulariosExternosPrivados', [TipoCasoFormulasController::class, 'listFormulariosExternosPrivados']);

    // ALMACENES-ZONAS CRM
    Route::get('/listAlmacenCrm', [AlmacenController::class, 'listAlmacenCrm']);

    Route::get('/listAnalistas/{tableroId}', [UserController::class, 'listAnalistas']);
    Route::post('/addCaso', [CasoController::class, 'add']);
    Route::post('/addCaso2', [CasoController::class, 'addCaso2']);
    Route::post('/addClienteOpenceo', [ClienteDynamoController::class, 'add']);
    Route::get('/getEstadoActualCaso/{caso_id}', [CasoController::class, 'getEstadoActualCaso']);

    // API GEX (JAIRO) - Esto se conecta con el PRECIADOR para cuando cotizan un producto con GEX (Check "PRODUCTO GEX:")
    Route::post('/facturaGex', [GEXController::class, 'facturaGex']);
    Route::post('/devuelveGex', [GEXController::class, 'devuelveGex']);
});

Route::group(["prefix" => "formulario"], function ($router) {
    Route::post('/addCDFormulario', [Formulario2Controller::class, 'addCDFormulario']);
    Route::get('/listFormulario2/{id}', [Formulario2Controller::class, 'listFormulario2']);
    Route::get('/listFormulaByIdForm/{form_id}', [Formulario2Controller::class, 'listFormulaByIdForm']);

    Route::get('/listCliente_byIdentificacion/{identificacion}', [Formulario2Controller::class, 'listCliente_byIdentificacion']); // lista del cliente por cedula

});

// ----------------- END RUTAS SIN TOKEN -------------
// ----------------- END RUTAS SIN TOKEN -------------
// ----------------- END RUTAS SIN TOKEN -------------







// ---------------------------- START VA LAS RUTAS PROTEGIDAS ----------------------------------------------------
// ---------------------------- START VA LAS RUTAS PROTEGIDAS ----------------------------------------------------
// ---------------------------- START VA LAS RUTAS PROTEGIDAS ----------------------------------------------------

Route::group(['prefix' => 'crm', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {
    // ? START POLITICAS
    Route::get('/listAllPoliticas', [PoliticaController::class, 'listAllPoliticas']);
    Route::post('/addPolitica', [PoliticaController::class, 'addPolitica']);
    Route::post('/editPolitica/{id}', [PoliticaController::class, 'editPolitica']);
    // ? END POLITICAS

    // ? START COBRANZAS
    Route::get('/diferenciasFechasDynamoNovasoft', [CobranzasController::class, 'diferenciasFechasDynamoNovasoft']);
    // ? END COBRANZAS

    // ? START API TESTER
    // Colecciones
    Route::get('/colecciones', [ApiController::class, 'listColecciones']);
    Route::post('/colecciones', [ApiController::class, 'addColeccion']);
    Route::put('/colecciones/{id}', [ApiController::class, 'editColeccion']);
    Route::delete('/colecciones/{id}', [ApiController::class, 'deleteColeccion']);

    // Requests
    Route::get('/requests/{coleccionId}', [ApiController::class, 'listRequests']);
    Route::post('/requests', [ApiController::class, 'addRequest']);
    Route::put('/requests/{id}', [ApiController::class, 'editRequest']);
    Route::delete('/requests/{id}', [ApiController::class, 'deleteRequest']);

    // Historial
    Route::get('/historial', [ApiController::class, 'listHistorial']);
    Route::delete('/historial/clear', [ApiController::class, 'clearHistorial']);
    Route::delete('/historial/{id}', [ApiController::class, 'deleteHistorial']);

    // Variables
    Route::get('/variables', [ApiController::class, 'listVariables']);
    Route::post('/variables', [ApiController::class, 'addVariable']);
    Route::put('/variables/{id}', [ApiController::class, 'editVariable']);
    Route::delete('/variables/{id}', [ApiController::class, 'deleteVariable']);

    // Ejecutar
    Route::post('/ejecutar', [ApiController::class, 'ejecutarRequest']);
    // ? END API TESTER



    // ! SERVIDOR SSH
    Route::get('/listServidores',           [ServidorController::class, 'listarServidores']);
    Route::get('/getStatsAllServidores',    [ServidorController::class, 'getStatsAllServidores']);
    Route::post('/reiniciarServidor',       [ServidorController::class, 'reiniciarServidor']);



    // ? START CAMBIO VENDEDORES AGENCIA
    Route::get('/listAlmacenesDynamo', [CambioAgenciaController::class, 'listAlmacenesDynamo']);
    Route::post('/getVendedorByIdentificacion', [CambioAgenciaController::class, 'getVendedorByIdentificacion']);
    Route::post('/editAgenciaVendedor', [CambioAgenciaController::class, 'editAgenciaVendedor']);
    // ? END CAMBIO VENDEDORES AGENCIA



    // ! START GESTION CLIENTES

    // ZONAS
    Route::get('/listAllZonas', [ZonaController::class, 'listAllZonas']);
    Route::get('/listZonasActivas', [ZonaController::class, 'listZonasActivas']);
    Route::get('/getZonaById/{id}', [ZonaController::class, 'getZonaById']);
    Route::post('/addZona', [ZonaController::class, 'addZona']);
    Route::post('/editZona/{id}', [ZonaController::class, 'editZona']);
    Route::delete('/deleteZona/{id}', [ZonaController::class, 'deleteZona']);

    // CLIENTES
    Route::get('/listAllClientes', [ClienteController::class, 'listAllClientes']);
    Route::get('/listClientesActivos', [ClienteController::class, 'listClientesActivos']);
    Route::get('/listClientesByUsuario', [ClienteController::class, 'listClientesByUsuario']);
    Route::get('/getClienteById/{id}', [ClienteController::class, 'getClienteById']);
    Route::post('/addCliente', [ClienteController::class, 'addCliente']);
    Route::post('/editCliente/{id}', [ClienteController::class, 'editCliente']);
    Route::delete('/deleteCliente/{id}', [ClienteController::class, 'deleteCliente']);
    Route::post('/asignarUsuarioCliente/{id}', [ClienteController::class, 'asignarUsuarioCliente']);
    Route::post('/desasignarUsuarioCliente/{id}', [ClienteController::class, 'desasignarUsuarioCliente']);
    Route::post('/importClientesExcel', [ClienteController::class, 'importClientesExcel']);

    // CONTACTOS
    Route::get('/listContactosByCliente/{clienteId}', [ContactoController::class, 'listContactosByCliente']);
    Route::get('/listContactosActivosByCliente/{clienteId}', [ContactoController::class, 'listContactosActivosByCliente']);
    Route::get('/getContactoById/{id}', [ContactoController::class, 'getContactoById']);
    Route::post('/addContacto', [ContactoController::class, 'addContacto']);
    Route::post('/editContacto/{id}', [ContactoController::class, 'editContacto']);
    Route::delete('/deleteContacto/{id}', [ContactoController::class, 'deleteContacto']);

    // CARGOS
    Route::get('/listAllCargos', [CargoController::class, 'listAllCargos']);

    // ACTIVIDADES
    Route::get('/listAllActividades', [ActividadClienteController::class, 'listAllActividades']);
    Route::get('/listActividadesByCliente/{clienteId}', [ActividadClienteController::class, 'listActividadesByCliente']);
    Route::get('/listActividadesPendientesByCliente/{clienteId}', [ActividadClienteController::class, 'listActividadesPendientesByCliente']);
    Route::get('/listActividadesTerminadasByCliente/{clienteId}', [ActividadClienteController::class, 'listActividadesTerminadasByCliente']);
    Route::get('/getActividadById/{id}', [ActividadClienteController::class, 'getActividadById']);
    Route::post('/addActividad', [ActividadClienteController::class, 'addActividad']);
    Route::post('/editActividad/{id}', [ActividadClienteController::class, 'editActividad']);
    Route::post('/cerrarActividad/{id}', [ActividadClienteController::class, 'cerrarActividad']);
    Route::post('/reasignarActividad/{id}', [ActividadClienteController::class, 'reasignarActividad']);
    Route::delete('/deleteActividad/{id}', [ActividadClienteController::class, 'deleteActividad']);

    Route::get('/listActividadesPendientesByUsuario/{fechaInicio}/{fechaFin}', [ActividadClienteController::class, 'listActividadesPendientesByUsuario']);
    Route::get('/listActividadesTerminadasByUsuario/{fechaInicio}/{fechaFin}', [ActividadClienteController::class, 'listActividadesTerminadasByUsuario']);
    Route::get('/listActividadesPendientesByUsuarioPorCampo/{tipo_campo}/{valor}', [ActividadClienteController::class, 'listActividadesPendientesByUsuarioPorCampo']);
    Route::get('/listActividadesTerminadasByUsuarioPorCampo/{tipo_campo}/{valor}', [ActividadClienteController::class, 'listActividadesTerminadasByUsuarioPorCampo']);

    // TIPOS DE ACTIVIDAD
    Route::get('/listAllTiposActividad', [TipoActividadController::class, 'listAllTiposActividad']);
    Route::get('/listTiposActividadActivos', [TipoActividadController::class, 'listTiposActividadActivos']);
    Route::post('/addTipoActividad', [TipoActividadController::class, 'addTipoActividad']);
    Route::post('/editTipoActividad/{id}', [TipoActividadController::class, 'editTipoActividad']);
    Route::delete('/deleteTipoActividad/{id}', [TipoActividadController::class, 'deleteTipoActividad']);

    // PRIORIDADES
    Route::get('/listAllPrioridades', [PrioridadActividadController::class, 'listAllPrioridades']);
    Route::get('/listPrioridadesActivas', [PrioridadActividadController::class, 'listPrioridadesActivas']);
    Route::post('/addPrioridad', [PrioridadActividadController::class, 'addPrioridad']);
    Route::post('/editPrioridad/{id}', [PrioridadActividadController::class, 'editPrioridad']);
    Route::delete('/deletePrioridad/{id}', [PrioridadActividadController::class, 'deletePrioridad']);

    // ESTADOS DE ACTIVIDAD
    Route::get('/listAllEstados', [EstadoActividadController::class, 'listAllEstados']);
    Route::get('/listEstadosActivos', [EstadoActividadController::class, 'listEstadosActivos']);
    Route::post('/addEstado', [EstadoActividadController::class, 'addEstado']);
    Route::post('/editEstado/{id}', [EstadoActividadController::class, 'editEstado']);
    Route::delete('/deleteEstado/{id}', [EstadoActividadController::class, 'deleteEstado']);

    // ADJUNTOS
    Route::get('/listAdjuntosByCliente/{clienteId}', [AdjuntoClienteController::class, 'listAdjuntosByCliente']);
    Route::get('/listAdjuntosByActividad/{actividadId}', [AdjuntoClienteController::class, 'listAdjuntosByActividad']);
    Route::get('/getAdjuntoById/{id}', [AdjuntoClienteController::class, 'getAdjuntoById']);
    Route::post('/addAdjunto', [AdjuntoClienteController::class, 'addAdjunto']);
    Route::post('/editAdjunto/{id}', [AdjuntoClienteController::class, 'editAdjunto']);
    Route::delete('/deleteAdjunto/{id}', [AdjuntoClienteController::class, 'deleteAdjunto']);

    // TIPOS DE ADJUNTO
    Route::get('/listAllTiposAdjunto', [TipoAdjuntoController::class, 'listAllTiposAdjunto']);
    Route::get('/listTiposAdjuntoActivos', [TipoAdjuntoController::class, 'listTiposAdjuntoActivos']);
    Route::post('/addTipoAdjunto', [TipoAdjuntoController::class, 'addTipoAdjunto']);
    Route::post('/editTipoAdjunto/{id}', [TipoAdjuntoController::class, 'editTipoAdjunto']);
    Route::delete('/deleteTipoAdjunto/{id}', [TipoAdjuntoController::class, 'deleteTipoAdjunto']);

    // ETIQUETAS
    Route::get('/listEtiquetasByActividad/{actividadId}', [EtiquetaActividadController::class, 'listEtiquetasByActividad']);
    Route::post('/addEtiquetaActividad', [EtiquetaActividadController::class, 'addEtiquetaActividad']);
    Route::post('/editEtiquetaActividad/{id}', [EtiquetaActividadController::class, 'editEtiquetaActividad']);
    Route::delete('/deleteEtiquetaActividad/{id}', [EtiquetaActividadController::class, 'deleteEtiquetaActividad']);

    // BITÁCORA
    Route::get('/listBitacora', [GestionClientesBitacoraController::class, 'listBitacora']);
    Route::get('/getBitacoraById/{id}', [GestionClientesBitacoraController::class, 'getBitacoraById']);
    Route::get('/getBitacoraByActividadClienteId/{id}', [GestionClientesBitacoraController::class, 'getBitacoraByActividadClienteId']);

    // SEMÁFOROS (cabecera)
    Route::get('/listAllSemaforos', [CSemaforoController::class, 'listAllSemaforos']);
    Route::get('/listSemaforosActivos', [CSemaforoController::class, 'listSemaforosActivos']);
    Route::post('/addSemaforo', [CSemaforoController::class, 'addSemaforo']);
    Route::post('/editSemaforo/{id}', [CSemaforoController::class, 'editSemaforo']);
    Route::delete('/deleteSemaforo/{id}', [CSemaforoController::class, 'deleteSemaforo']);

    // RANGOS DE SEMÁFORO (detalle)
    Route::get('/listDSemaforosByCSemaforo/{csemaforoId}', [DSemaforoController::class, 'listDSemaforosByCSemaforo']);
    Route::post('/addDSemaforo', [DSemaforoController::class, 'addDSemaforo']);
    Route::post('/editDSemaforo/{id}', [DSemaforoController::class, 'editDSemaforo']);
    Route::delete('/deleteDSemaforo/{id}', [DSemaforoController::class, 'deleteDSemaforo']);

    // ! END GESTION CLIENTES






    // !Tableau
    Route::get('/generateTokenTableau', [TableauAuthController::class, 'generateTokenTableau']);
    // !Power BI
    Route::post('/refreshDataset', [PowerBiRefreshDatasetController::class, 'refreshDataset']);
    Route::post('/getRefreshStatus', [PowerBiRefreshDatasetController::class, 'getRefreshStatus']);
    // !Google Sheets
    Route::get('/googleSheets/read', [GoogleSheetsController::class, 'read']);
    Route::post('/googleSheets/read', [GoogleSheetsController::class, 'read']);
    Route::get('/googleSheets/batchRead', [GoogleSheetsController::class, 'batchRead']);
    Route::post('/googleSheets/batchRead', [GoogleSheetsController::class, 'batchRead']);

    // GESTIONES
    Route::get('/listGestionByIdentificacion/{factura}', [GestionController::class, 'listGestionByIdentificacion']);
    Route::get('/listGestionesByIdAgencia', [GestionController::class, 'listGestionesByIdAgencia']);
    Route::post('/addGestion', [GestionController::class, 'addGestion']);
    Route::get('/listAllGestiones', [GestionController::class, 'listAllGestiones']);

    // GALERIA

    Route::post('/addGaleria/{caso_id}', [GaleriaController::class, 'addGaleria']); // Guardar la imagen
    Route::get('/listGaleriaByCasoId/{id}/{tabId}', [GaleriaController::class, 'listGaleriaByCasoId']); // Listar las imagenes
    Route::post('/editGaleria/{id}', [GaleriaController::class, 'editGaleria']); // Edita la imagen
    Route::delete('/deleteGaleria/{id}', [GaleriaController::class, 'deleteGaleria']); // Elimina la imagen
    Route::get('/listGaleriaBySolicitudCreditoId/{id}', [GaleriaController::class, 'listGaleriaBySolicitudCreditoId']); // Listar las imagenes
    Route::get('/listAllAdjuntosByCasoId/{caso_id}', [GaleriaController::class, 'listAllAdjuntosByCasoId']); // Lista Todos los archivos e imagenes del caso

    Route::get('/allTipoGaleria', [TipoGaleriaController::class, 'allTipoGaleria']); // Listar los tipos de imagenes

    // FONDO TABLERO

    Route::get('/listFondoTablero/{tab_id}', [GaleriaController::class, 'listFondoTablero']); // Listar las imagenes
    Route::post('/addFondoTablero/{tab_id}', [GaleriaController::class, 'addFondoTablero']); // Guardar la imagen
    Route::post('/editFondoTablero/{id}', [GaleriaController::class, 'editFondoTablero']); // Edita la imagen
    Route::delete('/deleteFondoTablero/{id}', [GaleriaController::class, 'deleteFondoTablero']); // Elimina la imagen

    // ARCHIVO

    Route::post('/addArchivo/{caso_id}', [ArchivoController::class, 'addArchivo']); // Guardar
    Route::post('/addArchivo2/{caso_id}', [ArchivoController::class, 'addArchivo2']); // Guardar
    Route::post('/addArrayArchivos/{caso_id}', [ArchivoController::class, 'addArrayArchivos']); // Guardar
    Route::get('/listArchivoByCasoId/{id}/{tableroListaId}', [ArchivoController::class, 'listArchivoByCasoId']); // Listar
    Route::post('/editArchivo/{id}', [ArchivoController::class, 'editArchivo']); // Editar
    Route::post('/editArchivo2/{id}', [ArchivoController::class, 'editArchivo2']); // Editar
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
    // Route::get('/listTableroMisCasos/{user_id}', [TableroController::class, 'listTableroMisCasos']); // listar tablero mis casos
    Route::post('/editTablero/{id}', [TableroController::class, 'editTablero']); // Editar
    Route::get('/listAllTableros', [TableroController::class, 'listAll']); // listar tablero mis casos
    Route::get('/listAllTablerosActivos', [TableroController::class, 'listAllTablerosActivos']); // listar tableros inactivos
    Route::get('/listAllTablerosInactivos', [TableroController::class, 'listAllTablerosInactivos']); // listar tableros inactivos
    Route::get('/listAllTablerosWithFases', [TableroController::class, 'listAllTablerosWithFases']); // listar tableros con sus fases
    Route::get('/listByTablerosIdWithFases/{tab_id}', [TableroController::class, 'listByTablerosIdWithFases']); // listar tableros con sus fases
    Route::get('/editMiembrosByTableroId/{id}', [TableroController::class, 'editMiembrosByTableroId']); // Editar los miembros del tablero
    Route::get('/usuariosTablero/{tabId}', [TableroController::class, 'usuariosTablero']); // usuariosTablero
    Route::get('/listTableroByDepId/{dep_id}', [TableroController::class, 'listTableroByDepId']); // listar

    Route::get('/permisoTableroUsuario/{tab_id}/{user_id}', [TableroController::class, 'permisoTableroUsuario']); // pemiso del usuario tablero
    Route::get('/listTablerosByUserId/{user_id}', [TableroController::class, 'listTablerosByUserId']); // lista de tableros por user_id

    // !START MIS CASOS
    Route::get('/listTableroMisCasosPendientes/{fechaInicio}/{fechaFin}/{user_id}', [TableroController::class, 'listTableroMisCasosPendientes']);
    Route::get('/listTableroMisCasosTerminados/{fechaInicio}/{fechaFin}/{user_id}', [TableroController::class, 'listTableroMisCasosTerminados']);
    Route::get('/listTableroMisCasosRechazados/{fechaInicio}/{fechaFin}/{user_id}', [TableroController::class, 'listTableroMisCasosRechazados']);

    Route::get('/listTableroMisCasosPendientesPorCampo/{tipo_campo}/{valor}/{user_id}', [TableroController::class, 'listTableroMisCasosPendientesPorCampo']);
    Route::get('/listTableroMisCasosTerminadosPorCampo/{tipo_campo}/{valor}/{user_id}', [TableroController::class, 'listTableroMisCasosTerminadosPorCampo']);
    Route::get('/listTableroMisCasosRechazadosPorCampo/{tipo_campo}/{valor}/{user_id}', [TableroController::class, 'listTableroMisCasosRechazadosPorCampo']);
    // !END MIS CASOS



    // !START TODOS LOS CASOS
    // *por fechas
    Route::get('/listTodosLosCasosPendientesSuperUsuario/{fechaInicio}/{fechaFin}', [TableroController::class, 'listTodosLosCasosPendientesSuperUsuario']);
    Route::get('/listTodosLosCasosTerminadosSuperUsuario/{fechaInicio}/{fechaFin}', [TableroController::class, 'listTodosLosCasosTerminadosSuperUsuario']);
    Route::get('/listTodosLosCasosRechazadosSuperUsuario/{fechaInicio}/{fechaFin}', [TableroController::class, 'listTodosLosCasosRechazadosSuperUsuario']);
    Route::get('/listTodosLosCasosPendientesAdministrador/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listTodosLosCasosPendientesAdministrador']);
    Route::get('/listTodosLosCasosTerminadosAdministrador/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listTodosLosCasosTerminadosAdministrador']);
    Route::get('/listTodosLosCasosRechazadosAdministrador/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listTodosLosCasosRechazadosAdministrador']);
    Route::get('/listTodosLosCasosPendientesUsuarioComun/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listTodosLosCasosPendientesUsuarioComun']);
    Route::get('/listTodosLosCasosTerminadosUsuarioComun/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listTodosLosCasosTerminadosUsuarioComun']);
    Route::get('/listTodosLosCasosRechazadosUsuarioComun/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listTodosLosCasosRechazadosUsuarioComun']);

    // *por campo
    Route::get('/listTodosLosCasosPendientesSuperUsuarioPorCampo/{tipo_campo}/{valor}', [TableroController::class, 'listTodosLosCasosPendientesSuperUsuarioPorCampo']);
    Route::get('/listTodosLosCasosTerminadosSuperUsuarioPorCampo/{tipo_campo}/{valor}', [TableroController::class, 'listTodosLosCasosTerminadosSuperUsuarioPorCampo']);
    Route::get('/listTodosLosCasosRechazadosSuperUsuarioPorCampo/{tipo_campo}/{valor}', [TableroController::class, 'listTodosLosCasosRechazadosSuperUsuarioPorCampo']);
    Route::get('/listTodosLosCasosPendientesAdministradorPorCampo/{tipo_campo}/{valor}/{tab_id}', [TableroController::class, 'listTodosLosCasosPendientesAdministradorPorCampo']);
    Route::get('/listTodosLosCasosTerminadosAdministradorPorCampo/{tipo_campo}/{valor}/{tab_id}', [TableroController::class, 'listTodosLosCasosTerminadosAdministradorPorCampo']);
    Route::get('/listTodosLosCasosRechazadosAdministradorPorCampo/{tipo_campo}/{valor}/{tab_id}', [TableroController::class, 'listTodosLosCasosRechazadosAdministradorPorCampo']);
    Route::get('/listTodosLosCasosPendientesUsuarioComunPorCampo/{tipo_campo}/{valor}/{tab_id}', [TableroController::class, 'listTodosLosCasosPendientesUsuarioComunPorCampo']);
    Route::get('/listTodosLosCasosTerminadosUsuarioComunPorCampo/{tipo_campo}/{valor}/{tab_id}', [TableroController::class, 'listTodosLosCasosTerminadosUsuarioComunPorCampo']);
    Route::get('/listTodosLosCasosRechazadosUsuarioComunPorCampo/{tipo_campo}/{valor}/{tab_id}', [TableroController::class, 'listTodosLosCasosRechazadosUsuarioComunPorCampo']);
    // !END TODOS LOS CASOS



    // !START REASIGNAR CASOS
    Route::get('/listReasignarCasosPendientesSuperUsuario/{fechaInicio}/{fechaFin}', [TableroController::class, 'listReasignarCasosPendientesSuperUsuario']);
    Route::get('/listReasignarCasosPendientesAdministrador/{fechaInicio}/{fechaFin}/{tab_id}', [TableroController::class, 'listReasignarCasosPendientesAdministrador']);
    Route::get('/listReasignarCasosPendientesUsuarioComun/{fechaInicio}/{fechaFin}/{tab_id}/{user_id}', [TableroController::class, 'listReasignarCasosPendientesUsuarioComun']);
    // !END REASIGNAR CASOS

    // !START MOVER CASOS MASIVAMENTE
    Route::get('/listFasesByTableroId/{tab_id}', [TableroController::class, 'listFasesByTableroId']);
    Route::get('/listTiposCasoByTableroId/{tab_id}', [TableroController::class, 'listTiposCasoByTableroId']);
    Route::get('/listEstadosByTableroId/{tab_id}', [TableroController::class, 'listEstadosByTableroId']);
    Route::get('/listCasosByFiltros/{tab_id}/{fase_id}/{tipo_caso_id}/{estado_id}/{fechaInicio}/{fechaFin}', [TableroController::class, 'listCasosByFiltros']);
    Route::get('/listRespuestasCaso/{tab_id}/{tipo_caso_id}/{fas_id}/{estado_id}', [EstadosFormulasController::class, 'listRespuestasCaso']);
    // !END MOVER CASOS MASIVAMENTE

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
    Route::get('/listHistorialCasoAgrupadoTablero/{caso_id}', [CasoController::class, 'listHistorialCasoAgrupadoTablero']); // listado/ historico de los estados del caso
    Route::get('/listCasosPendientesByCliente/{identificacion}', [CasoController::class, 'listCasosPendientesByCliente']); // listado/ historico de los estados del caso
    Route::get('/listCasosTerminadosByCliente/{identificacion}', [CasoController::class, 'listCasosTerminadosByCliente']); // listado/ historico de los estados del caso
    Route::get('/listUsuarioComunCasosPendienteByCliente/{identificacion}', [CasoController::class, 'listUsuarioComunCasosPendienteByCliente']); // listado/ historico de los estados del caso
    Route::get('/listUsuarioComunCasosTerminadosByCliente/{identificacion}', [CasoController::class, 'listUsuarioComunCasosTerminadosByCliente']); // listado/ historico de los estados del caso
    Route::get('/listActividadesCategoria2ByUserId/{user_id}', [CasoController::class, 'listActividadesCategoria2ByUserId']); // listado/ historico de los estados del caso
    Route::post('/editDuracionCaso', [CasoController::class, 'editDuracionCaso']); // Editar la observación del caso
    Route::get('/listAllActividadesByUserId/{user_id}', [CasoController::class, 'listAllActividadesByUserId']); // listado/ historico de los estados del caso
    Route::post('/reasignarCasosMasivo', [CasoController::class, 'reasignarCasosMasivo']); // Editar la observación del caso
    Route::post('/editAccesoPublico/{caso_id}', [CasoController::class, 'editAccesoPublico']); // Editar la observación del caso
    Route::post('/editAccesoPublicoActividadCliente/{caso_id}', [CasoController::class, 'editAccesoPublicoActividadCliente']); // Editar la observación del caso

    // CTAREA

    Route::post('/addCTarea', [CTareaController::class, 'addCTarea']); // guardar
    Route::get('/listCTareas', [CTareaController::class, 'listCTareas']); // guardar
    Route::post('/editCTarea/{id}', [CTareaController::class, 'editCTarea']); // Edita la tarea
    Route::delete('/deleteCTarea/{id}', [CTareaController::class, 'deleteCTarea']); // Edita la tarea

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

    // TIPO CASO

    Route::post('/addTipoCaso', [TipoCasoController::class, 'addTipoCaso']); // guardar
    Route::get('listTipoCasoByIdTablero/{tab_id}', [TipoCasoController::class, 'listTipoCasoByIdTablero']); // listar
    Route::get('listAllTipoCaso', [TipoCasoController::class, 'listAllTipoCaso']); // listar
    Route::get('listTipoCasoByIdTableroEstadoActivo/{tab_id}', [TipoCasoController::class, 'listTipoCasoByIdTableroEstadoActivo']); // listar
    Route::get('listTipoCasoByIdCasoId/{caso_id}', [TipoCasoController::class, 'listTipoCasoByIdCasoId']); // listar
    Route::get('listByIdTipoCasoActivo/{tc_id}', [TipoCasoController::class, 'listByIdTipoCasoActivo']); // listar
    Route::post('/editTipoCaso/{id}', [TipoCasoController::class, 'editTipoCaso']); // Edita la actividad
    Route::delete('/deleteTipoCaso/{id}', [TipoCasoController::class, 'deleteTipoCaso']); // getByTipoCasIdFormu
    Route::get('/getByTipoCasIdFormu/{tcId}', [TipoCasoController::class, 'getByTipoCasIdFormu']); //
    Route::get('/getFormulaByTipoCasoId/{tcId}', [TipoCasoController::class, 'getFormulaByTipoCasoId']); // trae la formula del tipo de caso
    Route::get('/listTipoCasoByCategoria/{num_categoria}', [TipoCasoController::class, 'listTipoCasoByCategoria']); // trae los tipos de caso por categoria_caso

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
    Route::get('listUsers', [UserController::class, 'listUsers']); // lista de usuario para el calendario
    Route::post('/addUser', [UserController::class, 'addUser']); // guardar
    Route::post('/editUser/{id}', [UserController::class, 'editUser']); // Editar
    Route::delete('/deleteUser/{id}', [UserController::class, 'deleteUser']); // Eliminar
    Route::get('/listUsuariosByTableroId/{tablero_id}', [UserController::class, 'listUsuariosByTableroId']); // listar usuarios del tablero
    Route::get('/listUsuarioById/{user_id}', [UserController::class, 'listUsuarioById']); // listar usuario por ID

    Route::get('/listAlmacenes', [UserController::class, 'listAlmacenes']); // listar almacenes
    Route::get('/listAlmacenes2', [UserController::class, 'listAlmacenes2']); // listar almacenes

    Route::post('/editEnLineaUser/{user_id}', [UserController::class, 'editEnLineaUser']); // editar en linea del usuario

    // NOTIFICACIONES

    Route::get('/allByDepartamento/{id}', [NotificacionesController::class, 'allByDepartamento']);
    Route::get('/listByDepartamento/{id}', [NotificacionesController::class, 'listByDepartamento']);
    Route::post('/editLeidoNotificacion/{id}', [NotificacionesController::class, 'editLeidoNotificacion']);
    Route::post('/editLeidoAllNotificaciones/{id}', [NotificacionesController::class, 'editLeidoAllNotificaciones']);
    Route::get('/listNotificacionesCasoByUser_id', [NotificacionesController::class, 'listNotificacionesCasoByUser_id']);

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
    Route::get('/listEstadosFormulasByTabIdByTipoCasoId/{tab_id}/{tipo_caso_id}', [EstadosFormulasController::class, 'listEstadosFormulasByTabIdByTipoCasoId']); // listar
    Route::post('/addEstadosFormulas', [EstadosFormulasController::class, 'addEstadosFormulas']); // guardar
    Route::post('/editEstadosFormulas/{id}', [EstadosFormulasController::class, 'editEstadosFormulas']); // Editar
    Route::delete('/deleteEstadosFormulas/{id}', [EstadosFormulasController::class, 'deleteEstadosFormulas']); // Eliminar
    Route::get('/listarTiposCasoTablero/{tab_id}', [EstadosFormulasController::class, 'listarTiposCasoTablero']); // listar


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
    Route::delete('/deleteCasoById/{caso_id}', [CasoController::class, 'deleteCasoById']); // Eliminar caso por el id

    // TIPO CASO FORMULAS

    Route::get('/listTpoCasoFormulasById/{tab_id}/{tc_id}', [TipoCasoFormulasController::class, 'listTpoCasoFormulasById']); // listar por la llave tab_id y tc_id
    Route::get('/listTpoCasoFormulas', [TipoCasoFormulasController::class, 'listTpoCasoFormulas']); // listar all
    Route::get('/listTpoCasoFormulasActivos', [TipoCasoFormulasController::class, 'listTpoCasoFormulasActivos']); // listar activos
    Route::post('/addTipoCasoFormulas', [TipoCasoFormulasController::class, 'addTipoCasoFormulas']); // guardar
    Route::post('/editTipoCasoFormulas/{id}', [TipoCasoFormulasController::class, 'editTipoCasoFormulas']); // editar
    Route::delete('/deleteTipoCasoFormulas/{id}', [TipoCasoFormulasController::class, 'deleteTipoCasoFormulas']); // eliminar

    // TUTORIALES

    Route::get('/listTutoriales', [TutorialController::class, 'listTutoriales']); // Lista los tutoriales
    Route::post('/addTutorial', [TutorialController::class, 'addTutorial']);
    Route::post('/editTutorial/{id}', [TutorialController::class, 'editTutorial']);
    Route::delete('/deleteTutorial/{id}/{tipo_registro}', [TutorialController::class, 'deleteTutorial']);

    Route::get('/listTutorialesByUserId/{user_id}', [TutorialUsuarioController::class, 'listTutorialesByUserId']);
    Route::post('/addEditTutorialUsuarios', [TutorialUsuarioController::class, 'addEditTutorialUsuarios']);
    Route::post('/listUsuariosByArchivoGaleriaId', [TutorialUsuarioController::class, 'listUsuariosByArchivoGaleriaId']);

    // RENEGOCIACION
    Route::post('/validarFacturaRenegociacion', [DdocumentoController::class, 'validarFacturaRenegociacion']);
    Route::post('/renegociaciones/renegociacionesJuanNarvaezMasivo', [RenegociacionesController::class, 'renegociacionesJuanNarvaezMasivo']);

    // RENEGOCIACION CON SOLICITUD + APROBACION (workflow 2 usuarios)
    Route::get('/renegociaciones/facturasPorCedula/{cedula}', [RenegociacionesController::class, 'facturasPorCedula']);
    Route::get('/renegociaciones/productosPorFactura/{doctran}', [RenegociacionesController::class, 'productosPorFactura']);
    Route::post('/renegociaciones/solicitud/crear', [RenegociacionesController::class, 'crearSolicitud']);
    Route::post('/renegociaciones/solicitud/editar/{id}', [RenegociacionesController::class, 'editarSolicitud']);
    Route::get('/renegociaciones/solicitud/listar', [RenegociacionesController::class, 'listarSolicitudes']);
    Route::get('/renegociaciones/solicitud/listar-paginado', [RenegociacionesController::class, 'listarSolicitudesPaginado']);
    Route::get('/renegociaciones/solicitud/previsualizar/{id}', [RenegociacionesController::class, 'previsualizarSolicitud']);
    Route::post('/renegociaciones/solicitud/ejecutar', [RenegociacionesController::class, 'ejecutarSolicitudes']);
    Route::get('/renegociaciones/solicitud/previsualizar-reversion/{id}', [RenegociacionesController::class, 'previsualizarReversion']);
    Route::post('/renegociaciones/solicitud/revertir/{id}', [RenegociacionesController::class, 'revertirSolicitud']);
    Route::get('/renegociaciones/solicitud/auditoria/{id}', [RenegociacionesController::class, 'auditoriaSolicitud']);

    // PAGARE
    Route::post('/addHistorialPagare', [RenegociacionController::class, 'addHistorialPagare']); // add los doctran de openceo al crm
    Route::get('/listHistorialPagare/{ddo_doctran}', [RenegociacionController::class, 'listHistorialPagare']); // listar doctran del crm
    Route::get('/getPagareByCodigoHistorial/{codigo_historial}', [RenegociacionController::class, 'getPagareByCodigoHistorial']); // obtener pagare del crm

    // SERIES GENERADAS

    Route::post('/addSeriesGeneradas', [SeriesGeneradasController::class, 'addSeriesGeneradas']);

    // FASES
    Route::get('/listFasesByTableroId', [FaseController::class, 'listFasesByTableroId']);
    Route::get('/agenciasCrmUsuario', [FaseController::class, 'agenciasCrmUsuario']);

    // FASES 2
    Route::post('/listTableroCompleto', [FaseController2::class, 'listTableroCompleto']);

    // Route::get('/listDirectorioCrm', [DirectorioController::class, 'listDirectorioCrm']);
    Route::post('/addDirectorio', [DirectorioController::class, 'addDirectorio']); // guardar
    Route::post('/editDirectorio/{id}', [DirectorioController::class, 'editDirectorio']); // Editar
    Route::delete('/deleteDirectorio/{id}', [DirectorioController::class, 'deleteDirectorio']); // Eliminar

    // REPORTES LINK

    Route::get('/listAllReporteLink', [ReporteLinkController::class, 'listAllReporteLink']); // listar
    Route::post('/addReporteLink', [ReporteLinkController::class, 'addReporteLink']); // guardar
    Route::post('/editReporteLink/{id}', [ReporteLinkController::class, 'editReporteLink']); // Editar
    Route::delete('/deleteReporteLink/{id}', [ReporteLinkController::class, 'deleteReporteLink']); // Eliminar

    Route::get('/listUsuariosByReporteId/{id}', [ReporteLinkUsuariosController::class, 'listUsuariosByReporteId']); // listar
    Route::post('/addEditReporteLinkUsuarios', [ReporteLinkUsuariosController::class, 'addEditReporteLinkUsuarios']); // guardar

    Route::get('/listReporteLinkByUserId/{id}', [ReporteLinkController::class, 'listReporteLinkByUserId']); // listar
    Route::get('/listUsuActivosReportes', [ReporteLinkController::class, 'listUsuActivosReportes']); // listar
    Route::get('/listReportesByUsuId/{id}', [ReporteLinkController::class, 'listReportesByUsuId']); // listar
    Route::get('/listAllReportes', [ReporteLinkController::class, 'listAllReportes']); // listar
    Route::post('/editReporteUsuario', [ReporteLinkController::class, 'editReporteUsuario']);

    // ULTIMO ID

    Route::get('/addCasoOscarBravo', [UltimoIdController::class, 'addCasoOscarBravo']);

    // TAREAS 2

    Route::get('/listTareasByTipoCasoId/{tipo_caso_id}/{caso_id}', [Tareas2Controller::class, 'listTareasByTipoCasoId']);
    Route::post('/editTareas2/{tarea_id}', [Tareas2Controller::class, 'editTareas2']);

    // UsuarioAlmacenes

    Route::get('/listAlmacenesByUserId/{id}', [UsuarioAlmacenController::class, 'listAlmacenesByUserId']); // listar
    Route::post('/addEditUsuarioAlmacenes', [UsuarioAlmacenController::class, 'addEditUsuarioAlmacenes']); // guardar



    // ----------------------------------------------------------------------------------------------------------------------
    // ----------------------------------- START RUTAS FELIPAO --------------------------------------------------------------
    // ----------------------------------------------------------------------------------------------------------------------

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
    Route::put('/bloqueoCaso', [CasoController::class, 'bloqueoCaso']);
    Route::get('/casoById/{id}', [CasoController::class, 'casoById']);
    Route::put('/editCaUsAs', [CasoController::class, 'reasignarCaso']);
    Route::post('/respuestaCaso', [CasoController::class, 'respuestaCaso']);
    Route::post('/respuestaCasoMasivo', [CasoController::class, 'respuestaCasoMasivo']);
    Route::get('/depUserTablero/{casoId}', [CasoController::class, 'depUserTablero']);
    Route::get('/addCasoOPMICreativa/{cppId}', [CasoController::class, 'addCasoOPMICreativa']);
    Route::put('/actualizarCaso/{casoId}/{tabId}', [CasoController::class, 'actualizarCaso']); //
    Route::put('/asignarmeCaso/{casoId}/{userId}', [CasoController::class, 'asignarmeCaso']); //
    Route::post('/crearFormularioSoporte', [CasoController::class, 'crearFormularioSoporte']);

    Route::get('/getCasoFormulario', [CasoController::class, 'getCasoFormulario']); //

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
    Route::post('/solicitudCreditoVendedorGenerar', [ReqCasoController::class, 'solicitudCreditoVendedorGenerar']);
    Route::post('/solicitudCreditoVendedorReimprimir', [ReqCasoController::class, 'solicitudCreditoVendedorReimprimir']);
    Route::post('/solicitudCreditoVendedorPdf', [ReqCasoController::class, 'solicitudCreditoVendedorPdf']);
    Route::post('/solicitudCreditoVendedorGuardarPdf', [ReqCasoController::class, 'solicitudCreditoVendedorGuardarPdf']);
    Route::get('/listaReqCasoId/{casoId}', [ReqCasoController::class, 'listaReqCasoId']); //
    Route::get('/listReqCasoId/{casoId}', [ReqCasoController::class, 'listReqCasoId']);

    Route::post('/addTarea', [TareaController::class, 'add']);
    Route::get('/listTareas', [TareaController::class, 'list']);
    Route::get('/byCedula/{cedula}', [EntidadController::class, 'byCedula']); // Listar

    Route::post('/listaComentarios', [ComentariosController::class, 'listaComentarios']);
    Route::post('/guardarComentario', [ComentariosController::class, 'guardarComentario']);

    Route::get('/listUsuariosActivos', [UserController::class, 'listUsuariosActivos']);
    Route::get('/listUsuariosActivos2', [UserController::class, 'listUsuariosActivos2']);

    Route::get('/listCasosUsuarios/{tabId}', [TableroProcesosController::class, 'list']); //list

    /************************  OPENCEO   *********************** */
    Route::get('/clienteByCedula/{cedula}', [ClienteOpenceoController::class, 'byCedula']);
    Route::get('/listClientes/{parametro}', [ClienteOpenceoController::class, 'list']);
    Route::get('/clienteCasoList/{depId}', [ClienteOpenceoController::class, 'clienteCasoList']);
    Route::get('/solicitudByEntId/{entIdentificacion}/{userId}', [solicitudCreditoController::class, 'solicitudByEntId']);

    /************************  PEDIDO MOVIL OPENCEO   ****************** */
    Route::get('/getPedidoById/{cppId}', [PedidoMovilController::class, 'getPedidoById']);
    Route::get('/comprasCliente/{entId}', [DashboardController::class, 'comprasCliente']);


    // ----------------------------------------------------------------------------------------------------------------------
    // ------------------------------------ END RUTAS FELIPAO ---------------------------------------------------------------
    // ----------------------------------------------------------------------------------------------------------------------



    // -----------------------------------------------------------------------------------------
    // -----------------------------------------------------------------------------------------
    // ----------------------- START RUTAS JAIRO  ----------------------------------------------

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
    Route::get('/listadoClientes/{busqueda}', [PreIngresoController::class, 'clientes']);
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
    Route::get('/validaSerieInv/{producto}/{serie}/{tipo}', [InventarioController::class, 'validaSerieInv']);

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

    //Relacion Almacen Vendedor Gex
    Route::get('/listadoRelaAlmaVen', [RelacionAlamcenVendedorGexController::class, 'listado']);
    Route::get('/listadoAlmacenesRelaV', [RelacionAlamcenVendedorGexController::class, 'almacenes']);
    Route::get('/listadoVendedoresRela', [RelacionAlamcenVendedorGexController::class, 'vendedores']);
    Route::post('/grabaRelaAlmVen', [RelacionAlamcenVendedorGexController::class, 'grabaRelaAlmVen']);
    Route::get('/byRelaAlmVen/{almacen}/{vendedor}', [RelacionAlamcenVendedorGexController::class, 'byRelaAlmVen']);
    Route::get('/eliminaRelaAlmVen/{almacen}/{vendedor}', [RelacionAlamcenVendedorGexController::class, 'eliminaRelaAlmVen']);

    //Metas Gex
    Route::get('/listadoMeta', [MetasController::class, 'listado']);
    Route::get('/listadoAlmacenesMeta', [MetasController::class, 'almacenes']);
    Route::get('/listadoVendedoresMeta/{almacen}', [MetasController::class, 'vendedores']);
    Route::post('/grabaMeta', [MetasController::class, 'grabaMeta']);
    Route::get('/byMeta/{meta}/{almacen}', [MetasController::class, 'byMeta']);
    Route::get('/eliminaMeta/{meta}/{almacen}', [MetasController::class, 'eliminaMeta']);

    //Recalculos Metas Gex
    Route::get('/listadoMetaRecal', [MetasRecalcController::class, 'listado']);
    Route::get('/listadoAlmacenesMetaRecal', [MetasRecalcController::class, 'almacenes']);
    Route::get('/cargaInfoRecal/{almacen}/{mes}/{anio}', [MetasRecalcController::class, 'tomaInformacion']);
    Route::post('/grabaMetaRecal', [MetasRecalcController::class, 'grabaMetaRecal']);
    Route::get('/byMetaRecal/{metaRecal}/{almacen}', [MetasRecalcController::class, 'byMetaRecal']);
    Route::get('/eliminaMetaRecal/{metaRecal}/{almacen}', [MetasRecalcController::class, 'eliminaMetaRecal']);

    //Proceso de Metas
    Route::get('/listadoProcMeta', [ProcMetasController::class, 'listado']);
    Route::get('/listadoAlmacenesProcMeta', [ProcMetasController::class, 'almacenes']);
    Route::get('/procesaMetas/{almacen}/{mes}/{anio}', [ProcMetasController::class, 'procesaMetas']);
    Route::post('/grabaProcMeta', [ProcMetasController::class, 'grabaProcMeta']);
    Route::get('/byProcMeta/{cumpli}/{almacen}', [ProcMetasController::class, 'byProcMeta']);
    Route::get('/eliminaProcMeta/{cumpli}/{almacen}', [ProcMetasController::class, 'eliminaProcMeta']);

    //Comisiones
    Route::get('/listadoComisiones', [ComisionesController::class, 'listado']);
    Route::get('/byComision/{comi}', [ComisionesController::class, 'byComision']);
    Route::post('/grabaComi', [ComisionesController::class, 'grabaComi']);
    Route::get('/eliminaComision/{comi}', [ComisionesController::class, 'eliminaComision']);

    //Proceso de Comisiones
    Route::get('/listadoProcComi', [ProcComiController::class, 'listado']);
    Route::get('/listadoAlmacenesProcComi', [ProcComiController::class, 'almacenes']);
    Route::get('/listadoCumplimientos/{almacen}', [ProcComiController::class, 'cumplimientos']);
    Route::get('/procesaComisiones/{cumplimiento}/{almacen}', [ProcComiController::class, 'procesaComisiones']);
    Route::post('/grabaProcComi', [ProcComiController::class, 'grabaProcComi']);
    Route::get('/byProcComi/{comi}/{cumpli}/{almacen}', [ProcComiController::class, 'byProcComi']);
    Route::get('/eliminaProcComi/{comi}/{cumpli}/{almacen}', [ProcComiController::class, 'eliminaProcComi']);

    // ----------------------- END RUTAS JAIRO  -----------------------------------------------
    // ----------------------------------------------------------------------------------------
    // ----------------------------------------------------------------------------------------

});

Route::group(["prefix" => "formulario", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {

    // FORMULARIOS2

    Route::get('/listAll', [Formulario2Controller::class, 'listAll']);
    Route::get('/listFormByTipoCaso/{tipoCaso_id}', [Formulario2Controller::class, 'listFormByTipoCaso']);

    Route::post('/addEditFormulario', [Formulario2Controller::class, 'addEditFormulario']);
    Route::delete('/deleteFormulario/{id}', [Formulario2Controller::class, 'deleteFormulario']);
    Route::get('/listAllSinRelacion', [Formulario2Controller::class, 'listAllSinRelacion']);

    Route::get('/listCFormulario/{cForm_id}', [Formulario2Controller::class, 'listCFormulario']);
    Route::post('/editValorRespuesta/{id}', [Formulario2Controller::class, 'editValorRespuesta']); // Editar

    // Route::get('/listCliente_byIdentificacion/{identificacion}', [Formulario2Controller::class, 'listCliente_byIdentificacion']); // lista del cliente por cedula
    Route::get('/listFacturasPendientes/{fechaInicio}/{fechaFin}', [Formulario2Controller::class, 'listFacturasPendientes']); // lista de facturas
    Route::get('/listFacturasProcesadas/{fechaInicio}/{fechaFin}', [Formulario2Controller::class, 'listFacturasProcesadas']); // lista de facturas
    Route::post('/af_obtener_facturas_cliente', [Formulario2Controller::class, 'af_obtener_facturas_cliente']);
    Route::get('/listProductosActivosDynamo', [Formulario2Controller::class, 'listProductosActivosDynamo']);
    Route::get('/getCasoVacacionesByCasoId/{caso_id}', [Formulario2Controller::class, 'getCasoVacacionesByCasoId']);

    Route::get('/listFormulariosUsuarios', [Formulario2UsuariosController::class, 'listFormulariosUsuarios']); // Formularios Usuarios
    Route::post('/addEditFormulariosUsuarios', [Formulario2UsuariosController::class, 'addEditFormulariosUsuarios']); // Formularios Usuarios
    Route::get('/listFormulariosByUsuId/{usu_id}', [Formulario2UsuariosController::class, 'listFormulariosByUsuId']); // Formularios Usuarios
    Route::get('/listRespuestasByFormId/{form_id}', [Formulario2UsuariosController::class, 'listRespuestasByFormId']); // Formularios Usuarios

    Route::get('/af_cliente_dfactura/{identificacion}', [Formulario2UsuariosController::class, 'af_cliente_dfactura']); // Formularios Usuarios

    Route::get('/listAllClientesDynamo', [Formulario2Controller::class, 'listAllClientesDynamo']); // lista de clientes para actividades caso
    Route::get('/listClienteDynamoByIdentificacion/{identificacion}', [Formulario2Controller::class, 'listClienteDynamoByIdentificacion']); // lista de clientes para actividades caso
});

/************************  CHAT   ****************** */
Route::group(["prefix" => "chat", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/listConversaciones/{userId}', [ChatController::class, 'listConversaciones']);
    Route::get('/listarMensajes/{converId}/{tipoConver}/{numeroPagina}', [ChatController::class, 'listarMensajes']);
    Route::post('/enviarMensaje/{converId}/{tipoConver}', [ChatController::class, 'enviarMensaje']); //
    Route::delete('/eliminarMensaje/{mensajeId}/{converId}/{tipoConver}', [ChatController::class, 'eliminarMensaje']); //
    Route::put('/actualizarMensaje/{converId}/{tipoConver}/{accion}', [ChatController::class, 'actualizarMensaje']); //
    Route::get('/usuariosParaChat', [ChatController::class, 'usuariosParaChat']); //
    Route::post('/iniciarChatNormal', [ChatController::class, 'iniciarChatNormal']);
    Route::post('/iniciarChatGrupal', [ChatController::class, 'iniciarChatGrupal']); //
    Route::get('/getImagenesSmg', [ChatController::class, 'getImagenesSmg']); //
    Route::get('/getMensaje/{id}', [ChatController::class, 'getMensaje']); //
    Route::get('/listarMensajesNoLeidos', [ChatController::class, 'listarMensajesNoLeidos']);
    Route::get('/usersGrupoChat/{converId}', [ChatController::class, 'usersGrupoChat']); //
    Route::post('/actualizarGrupo', [ChatController::class, 'actualizarGrupo']); //
    // ARCHIVOS E IMAGENES PARA EL CHAT
    Route::post('/addGaleriaArchivosChat', [ChatController::class, 'addGaleriaArchivosChat']);
    Route::get('/listarArchivosConver/{converId}/{tipoChat}', [ChatArchivosController::class, 'listarArchivosConver']);
    Route::get('/listarGaleriaConver/{converId}/{tipoChat}', [ChatArchivosController::class, 'listarGaleriaConver']);
});
// FORMULARIO AUTORIZACION TRATAMIENTO DE DATOS

Route::group(["prefix" => "autorizaciones", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::post('/add', [FormAuthDatosCliController::class, 'add']);
    Route::get('/getAlmacenId/{id}', [FormAuthDatosCliController::class, 'getAlmacenId']);
    Route::get('/listAlmacenes', [FormAuthDatosCliController::class, 'listAlmacenes']);
    Route::get('/store', [FormAuthDatosCliController::class, 'store']);
    Route::get('/validarAutoTratDatos/{id}', [FormAuthDatosCliController::class, 'validarAutoTratDatos']);
});

Route::group(["prefix" => "crm/audi", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/cliTabAmortizacion/{cuentaanterior}', [ClienteAditoriaController::class, 'cliTabAmortizacion']);
});

Route::group(["prefix" => "crm/robot", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/reasignarCaso/{estadoFormId}/{casoId}/{tableroActualId}/{banMostrarVistaCreditoAprobado?}', [RobotCasoController::class, 'reasignarCaso']);
});

// CONSULTA DE IDENTIDAD POR CEDULA (fuentes externas: SRI oficial + Ecuador Legal)
Route::group(["prefix" => "consultas/identidad", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/consultarRucSri/{identificacion}', [ConsultaIdentidadExternoController::class, 'consultarRucSri']);
    Route::get('/consultarCedulaEcuadorLegal/{identificacion}', [ConsultaIdentidadExternoController::class, 'consultarCedulaEcuadorLegal']);
});

// FORMULARIOS FELIPE
Route::group(["prefix" => "form", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/list', [FormController::class, 'list']);
    Route::get('/storeA/{formId}', [FormController::class, 'storeA']);
    Route::get('/storeB/{formId}/{userId}', [FormController::class, 'storeB']);
    Route::get('/storeC/{casoId}', [FormController::class, 'storeC']);
    Route::get('/cargarFormulario/{formId}', [FormController::class, 'cargarFormulario']);
    Route::get('/listByDepar/{depId}/{userId}', [FormController::class, 'listByDepar']); //
    Route::get('/formUser/{depId}/{userId}', [FormController::class, 'formUser']); //formUser
    Route::get('/byId/{formId}', [FormController::class, 'byId']); //formUser
    Route::get('/listAll', [FormController::class, 'listAll']); //
    Route::get('/listAnonimos', [FormController::class, 'listAnonimos']);
    Route::get('/impresion/{formId}/{userId}', [FormController::class, 'impresion']); //impresion
    Route::put('/edit/{id}', [FormController::class, 'edit']); //
    Route::post('/addFormulario', [FormController::class, 'addFormulario']); //
    Route::get('listFormByIdTablero/{tab_id}', [FormController::class, 'listFormByIdTablero']); //
    Route::get('/storeCasoForm/{casoId}', [FormController::class, 'storeCasoForm']);

    Route::post('/guardarFormularioValores', [FormController::class, 'guardarFormularioValores']);
    Route::get('/cargarFormularioAyuda/{casoId}', [FormController::class, 'cargarFormularioAyuda']);
    Route::get('/getByTipoCasIdFormu/{formId}/{casoId}', [FormController::class, 'getByTipoCasIdFormu']);
});

Route::group(['prefix' => 'form/campo', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
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
    //Route::post('/addCampoValor', [CampoController::class, 'addCampoValor']); //addCampoValor
    //----------------------------------------------------------------CAMPOS COMPONENTES FORMULARIO DINAMICO
    Route::get('/loadInitialData', [ComponentsController::class, 'loadInitialData']);
    Route::post('/listarEntidades', [ComponentsController::class, 'listarEntidades']);
});

Route::group(['prefix' => 'form/seccion', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/store', [FormSeccionController::class, 'store']);
    Route::post('/add', [FormSeccionController::class, 'add']);
    Route::put('/edit/{id}', [FormSeccionController::class, 'edit']);
});

//----------------------- PARAMETROS DIRECCION ----------------------------------------------
Route::group(['prefix' => 'parametros', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/direccion', [ParametrosController::class, 'direccion']); //
    Route::get('/direccionParroquias/{cantonId}', [ParametrosController::class, 'direccionParroquias']); //direccionParroquias
});

Route::group(["prefix" => "credito", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {

    // RESUMEN DE CASOS DEL CLIENTE
    Route::post('/listResumenCasosClienteByIdentificacion', [ResumenController::class, 'listResumenCasosClienteByIdentificacion']); // Editar

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
    Route::post('/editReferenciaObservacion/{id}', [ReferenciasClienteController::class, 'editReferenciaObservacion']); // Editar
    Route::post('/editReferenciaValida/{id}', [ReferenciasClienteController::class, 'editReferenciaValida']); // Editar
    Route::delete('/deleteReferenciasCliente/{id}', [ReferenciasClienteController::class, 'deleteReferenciasCliente']); // Eliminar
    Route::get('/listReferenciasByClienteId/{cli_id}', [ReferenciasClienteController::class, 'listReferenciasByClienteId']); // lista
    Route::get('/listReferenciasByIdentificacion/{identificacion}', [ReferenciasClienteController::class, 'listReferenciasByIdentificacion']); // lista

    // parentesco CRM

    Route::get('/listParentesco', [ParentescoController::class, 'listParentesco']); // listar

    // Tipo Telefono CRM

    Route::get('/listTipoTelefono', [TipoTelefonoController::class, 'listTipoTelefono']); // listar

    // Email - correo electronico

    Route::post('/send_emailCambioFase/{caso_id}/{fase_id}', [EmailController::class, 'send_emailCambioFase']); // Envia un correo cuando cambia de fase
    Route::post('/send_emailLinkEnrolamiento', [EmailController::class, 'send_emailLinkEnrolamiento']); // Envia un correo con el link del enrolamiento
    Route::post('/test_email', [EmailController::class, 'test_email']); // Envia un correo cuando cambia de fase

    Route::get('/listEmailByFaseId/{fase_id}', [EmailController::class, 'listEmailByFaseId']); // lista el correo de la fase
    Route::post('/addEmail', [EmailController::class, 'addEmail']);
    Route::post('/editEmail/{id}', [EmailController::class, 'editEmail']);

    // Email Dinámico - Palabras clave y envío con reemplazo de {req:...} y {caso_*}
    Route::get('/getPlaceholdersByFase/{fase_id}', [EmailDinamicoController::class, 'getPlaceholdersByFase']);
    Route::post('/sendEmailDinamico/{caso_id}/{fase_id}', [EmailDinamicoController::class, 'sendEmailDinamico']);

    // FILTRAR CASOS RECHAZADOS Y TERMINADOS PARA QUE VEAN TODAS LAS ANALISTAS, ASI NO HAYA PARTICIPADO EN EL CASO

    Route::get('/listCasosRechazadosTerminados/{fechaInicio}/{fechaFin}/{tableroId}', [CasoController::class, 'listCasosRechazadosTerminados']);
});

//contratos gex felipe
Route::group(["prefix" => "gex-contrato", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/loadInitialData/{almId}', [ContratoGexController::class, 'loadInitialData']);
    Route::post('/generarContratoCRM', [ContratoGexController::class, 'generarContratoCRM']);
    Route::post('/validarSerieContrato', [ContratoGexController::class, 'validarSerieContrato']); //
    Route::get('/listarContratosBodega/{bod_id}', [ContratoGexController::class, 'listarContratosBodega']);
    Route::get('/listarTodosContratosBodega', [ContratoGexController::class, 'listarTodosContratosBodega']);
    Route::delete('/eliminarContratoId/{id}', [ContratoGexController::class, 'eliminarContratoId']);
    Route::get('/getContratoGexCrmId/{id}', [ContratoGexController::class, 'getContratoGexCrmId']);
});

Route::group(["prefix" => "gex", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {

    // SERIES ALM
    Route::get('/listBodegaSeriesGeneradas', [BodegaSeriesGeneradasController::class, 'listBodegaSeriesGeneradas']); // listar todas las bodegas
    Route::get('/listSeriesGeneradasByBodId', [BodegaSeriesGeneradasController::class, 'listSeriesGeneradasByBodId']); // listar todas las bodegas

    Route::delete('/borrarInventarioCompleto/{numero_inventario}', [SeriesAlmController::class, 'borrarInventarioCompleto']); // Borrar un inventario completo que no tenga despachos ni contratos
    Route::get('/listBodegas', [SeriesAlmController::class, 'listBodegas']); // listar todas las bodegas
    Route::get('/inventariosByBod_id/{bod_id}', [SeriesAlmController::class, 'inventariosByBod_id']); // listar todas las bodegas
    Route::delete('/borrarItemInventario/{numero_inventario}/{bod_id}/{pro_id}/{serie}/{tipo}', [SeriesAlmController::class, 'borrarItemInventario']); // Borrar un inventario completo que no tenga despachos ni contratos

    Route::get('/listNotasCreditoDespachos', [SeriesAlm2Controller::class, 'listNotasCreditoDespachos']); // listar los despachos que tengan notas de credito
    Route::post('/saldoProSeries', [SeriesAlmController::class, 'saldoProSeries']); // listar todas las bodegas

    Route::get('/dataSerieEliminar/{serie}', [SeriesAlmController::class, 'dataSerieEliminar']); // listar todas las bodegas

    Route::delete('/eliminarSerieContrato/{alm_id}/{numero}', [SeriesAlmController::class, 'eliminarSerieContrato']);
    Route::delete('/eliminarSerieDespacho/{numeroDes}/{serie}/{bodId}', [SeriesAlmController::class, 'eliminarSerieDespacho']);
    Route::delete('/eliminarSerieInventario/{numeroInv}/{serie}/{pro_id}/{tipo}/{ssBodId}', [SeriesAlmController::class, 'eliminarSerieInventario']);
    Route::delete('/eliminarSeriePreIngreso/{numeroInv}/{serie}/{pro_id}/{tipo}/{ssBodId}', [SeriesAlmController::class, 'eliminarSeriePreIngreso']);


    //toma de iinventario
    Route::get('/loadInitialData/{bodId}', [StockProSerieController::class, 'loadInitialData']); // listar los despachos que tengan notas de credito

    Route::get('/listSeries/{pro_id}/{bod_id}', [StockProSerieController::class, 'listSeries']);
    Route::post('/addSeriesInv', [StockProSerieController::class, 'addSeriesInv']);

    Route::get('/getReporteBodegas', [StockProSerieController::class, 'getReporteBodegas']);
    Route::get('/getSerieDesCliente/{serie}/{bodId}', [StockProSerieController::class, 'getSerieDesCliente']);
    Route::get('/getSerie/{serie}', [StockProSerieController::class, 'getSerie']);
    Route::get('/despacharSerie/{serie}/{bodId}', [StockProSerieController::class, 'despacharSerie']);
    Route::get('/reportePorBodegaId/{bodId}', [StockProSerieController::class, 'reportePorBodegaId']);
    // Route::get('/buscarProCodigo/{serie}', [StockProSerieController::class, 'buscarProCodigo']);
    Route::post('/buscarProCodigo', [StockProSerieController::class, 'buscarProCodigo']);
});

Route::group(["prefix" => "configuracion", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {

    // CODIGOS QR

    Route::get('/listAllCodigosQr', [CodigoQrController::class, 'listAllCodigosQr']);

    // ARCHIVOS 2

    Route::get('/listAllArchivos2', [Archivos2Controller::class, 'listAllArchivos2']);
    Route::post('/addArchivos2', [Archivos2Controller::class, 'addArchivos2']);
    Route::delete('/deleteArchivos2/{id}', [Archivos2Controller::class, 'deleteArchivos2']);

    // NOTAS

    Route::post('/addNotes', [NotesController::class, 'addNotes']); // guardar
    Route::get('/listNotes', [NotesController::class, 'listNotes']); // listar
    Route::post('/editNotes/{id}', [NotesController::class, 'editNotes']); // Editar
    Route::delete('/deleteNotes/{id}', [NotesController::class, 'deleteNotes']); // Eliminar

    // CORREOS

    Route::post('/addCorreo', [CorreoController::class, 'addCorreo']); // guardar
    Route::get('/listCorreos', [CorreoController::class, 'listCorreos']); // listar
    Route::post('/editCorreo/{id}', [CorreoController::class, 'editCorreo']); // Editar
    Route::delete('/deleteCorreo/{id}', [CorreoController::class, 'deleteCorreo']); // Eliminar

    // AGENCIA

    Route::get('/listAgenciasActivas', [AgenciaController::class, 'listAgenciasActivas']); // listar
    Route::get('/listAllAgencias', [AgenciaController::class, 'listAllAgencias']); // listar
    Route::post('/addAgencia', [AgenciaController::class, 'addAgencia']); // guardar
    Route::post('/editAgencia/{id}', [AgenciaController::class, 'editAgencia']); // Editar
    Route::delete('/deleteAgencia/{id}', [AgenciaController::class, 'deleteAgencia']); // Eliminar
    Route::get('/listAgenciasActivas2', [AgenciaController::class, 'listAgenciasActivas2']); // listar


    // ZONAS AGENCIAS
    Route::get('/listZonasAgenciaActivas', [ZonaAgenciaController::class, 'listZonasAgenciaActivas']);


    // HORARIOS
    Route::get('/listAllHorarios', [HorarioController::class, 'listAllHorarios']);
    Route::get('/listDhorarioById/{id}', [HorarioController::class, 'listDhorarioById']);
    Route::post('/addCDHorario', [HorarioController::class, 'addCDHorario']);
    Route::post('/editCDHorario/{id}', [HorarioController::class, 'editCDHorario']);
    Route::delete('/deleteCDHorario/{id}', [HorarioController::class, 'deleteCDHorario']);

    Route::get('/listHorariosActivos', [HorarioController::class, 'listHorariosActivos']);
    Route::get('/getHorarioUsuario/{user_id}', [HorarioController::class, 'getHorarioUsuario']);
    Route::post('/editUserHorario', [HorarioController::class, 'editUserHorario']);
});

Route::group(["prefix" => "openceo", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {

    // openceo

    Route::get('/buscarEntidadEmpleado/{identificacion}', [EntidadDynamoController::class, 'buscarEntidadEmpleado']);
    Route::get('/listAlmacenesPuntoVenta', [EntidadDynamoController::class, 'listAlmacenesPuntoVenta']);
    Route::get('/listAllMenusDynamo', [EntidadDynamoController::class, 'listAllMenusDynamo']);
    Route::get('/listEmpleadoByIdentificacion/{identificacion}', [EntidadDynamoController::class, 'listEmpleadoByIdentificacion']);
    Route::get('/listBodegas', [BodegaController::class, 'listBodegas']);

    // CLIENTE (réplica mntClienteAux vía crm.fn_clientes_registrar / crm.fn_clientes_modificar)
    Route::post('/crearCliente', [Cliente2Controller::class, 'crear']);
    Route::post('/modificarCliente', [Cliente2Controller::class, 'modificar']);
    Route::get('/catalogosCliente', [Cliente2Controller::class, 'catalogos']);
    Route::get('/catalogoPaginado', [Cliente2Controller::class, 'catalogoPaginado']);
    Route::get('/ubicacionArbol', [Cliente2Controller::class, 'ubicacionArbol']);
    Route::get('/parroquiasByCanton/{ctnId}', [Cliente2Controller::class, 'parroquiasByCanton']);
    Route::get('/cantonesByProvincia/{prvId}', [Cliente2Controller::class, 'cantonesByProvincia']);
    Route::get('/empresaInfo/{comId}', [Cliente2Controller::class, 'empresaInfo']);
    // EMPRESA (compania): crear/editar desde el modal de cliente
    Route::post('/companiaCrear', [Cliente2Controller::class, 'companiaCrear']);
    Route::post('/companiaModificar', [Cliente2Controller::class, 'companiaModificar']);
    Route::get('/companiaBuscar/{comId}', [Cliente2Controller::class, 'companiaBuscar']);
    Route::get('/companiaEsEditable', [Cliente2Controller::class, 'companiaEsEditable']);
    Route::get('/listarClientesDynamo', [Cliente2Controller::class, 'listar']);
    Route::get('/solicitudCreditoCliente/{cliId}', [Cliente2Controller::class, 'solicitudCredito']);
    Route::get('/buscarClienteIdentificacion', [Cliente2Controller::class, 'buscarPorIdentificacion']);
    Route::get('/clienteAuditoria', [Cliente2Controller::class, 'clienteAuditoria']);
    Route::get('/clienteSolicitudCreditoHistorial', [Cliente2Controller::class, 'solicitudCreditoHistorial']);

    // DOCUMENTACIÓN CLIENTE (listar casos por agencia para reimprimir documentos)
    Route::get('/listar_casos_clientes_by_agencia', [DocumentacionClienteController::class, 'listar_casos_clientes_by_agencia']);
    Route::get('/reimprimir_solicitud_cupo', [DocumentacionClienteController::class, 'reimprimir_solicitud_cupo']);
    Route::get('/datos_titular_autorizacion', [DocumentacionClienteController::class, 'datos_titular_autorizacion']);
});

Route::group(["prefix" => "parametro", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::get('/listFormulaByParametro', [ParametroController::class, 'listFormulaByParametro']);
    Route::get('/parametroFDias', [ParametroController::class, 'parametroFDias']);
    Route::get('/parametroFDiasMisCasos', [ParametroController::class, 'parametroFDiasMisCasos']);
    Route::get('/parametroFDiasTodosLosCasos', [ParametroController::class, 'parametroFDiasTodosLosCasos']);
});

// PERFILES, MENU, ACCESOS

Route::group(['prefix' => 'profile', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {
    Route::get('all', [ProfileController::class, 'all']);
    Route::get('list', [ProfileController::class, 'list']);
    Route::get('list/{id}', [ProfileController::class, 'findById']);
    Route::post('create', [ProfileController::class, 'create']);
    Route::post('edit/{id}', [ProfileController::class, 'edit']);
    Route::delete('deleteProfile/{id}', [ProfileController::class, 'deleteProfile']);
    Route::post('clonProfile', [ProfileController::class, 'clonProfile']);
    Route::get('buscarAccesosByProfileId/{id}', [ProfileController::class, 'buscarAccesosByProfileId']);
});

Route::group(['prefix' => 'access', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {
    Route::get('program/{profile}/{program}', [ProfileController::class, 'findByProgram']);
    Route::get('menu/{userid}', [ProfileController::class, 'findByUser']);
});

Route::group(['prefix' => 'company', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {
    Route::get('lista/{id}', [CompanyController::class, 'findById']);
    Route::put('editar/{id}', [CompanyController::class, 'edit']);
});

Route::group(['prefix' => 'menu', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {
    Route::get('list', [MenuController::class, 'list']);
    Route::get('list/{id}', [MenuController::class, 'findById']);

    Route::post('addMenu', [MenuController::class, 'addMenu']);
    Route::post('editMenu/{id}', [MenuController::class, 'editMenu']);
    Route::delete('deleteMenu/{id}', [MenuController::class, 'deleteMenu']);
});

Route::group(["prefix" => "activos", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {

    // ACTIVOS

    Route::get('/listAllActivos', [ActivosController::class, 'listAllActivos']);
    Route::get('/listActivos', [ActivosController::class, 'listActivos']);
    Route::post('/addActivo', [ActivosController::class, 'addActivo']);
    Route::post('/editActivo/{id}', [ActivosController::class, 'editActivo']);
    Route::delete('/deleteActivo/{id}', [ActivosController::class, 'deleteActivo']);
    Route::get('/historyActivo/{id}', [ActivosController::class, 'historyActivo']);

    Route::get('/listTipoActivos', [ActivosController::class, 'listTipoActivos']);
    Route::get('/listEstadoActivos', [ActivosController::class, 'listEstadoActivos']);
    Route::get('/listMarca', [ActivosController::class, 'listMarca']);
    Route::get('/listLocalidades', [ActivosController::class, 'listLocalidades']);

    // ACTAS

    Route::get('/listAllActas', [ActasController::class, 'listAllActas']);
    Route::get('/getActivosByNumeroActa/{numero}', [ActasController::class, 'getActivosByNumeroActa']);
    Route::post('/addActa', [ActasController::class, 'addActa']);
    Route::post('/editActa/{numero}', [ActasController::class, 'editActa']);
    Route::post('/editRecepcionFisica/{numero}', [ActasController::class, 'editRecepcionFisica']);
    Route::post('/editRecibidoPor/{numero}', [ActasController::class, 'editRecibidoPor']);
});

// ------------------------------ END RUTAS PROTEGIDAS ------------------------------------------------------
// ------------------------------ END RUTAS PROTEGIDAS ------------------------------------------------------
// ------------------------------ END RUTAS PROTEGIDAS ------------------------------------------------------




















Route::group(["prefix" => "almacenesespana"], function ($router) {

    // ============================================================
    // PIN PAD MEDIANET — endpoints REST
    // ============================================================

    // ----- Utilidades / Diagnostico -----
    // GET /probe : verifica que el Pin Pad responde TCP (no envia trama real)
    Route::get('/pinpad/probe', [PinpadController::class, 'probe']);
    // GET /hash  : genera un hash 3DES de prueba (test del cifrado)
    Route::get('/pinpad/hash', [PinpadController::class, 'hash']);
    // POST /raw  : envia una trama YA armada (debug o desde el JAR oficial)
    Route::post('/pinpad/raw', [PinpadController::class, 'raw']);

    // ----- PP — Proceso de Pago (transacciones de venta) -----
    // 5 modalidades: corriente, diferido, anulacion, reverso, maxidolar
    Route::post('/pinpad/cobrar', [PinpadController::class, 'cobrar']);
    Route::post('/pinpad/diferido', [PinpadController::class, 'diferido']);
    Route::post('/pinpad/anular', [PinpadController::class, 'anular']);
    Route::post('/pinpad/reverso', [PinpadController::class, 'reverso']);
    // GET liviano: chequea si hay un reverso cacheado, sin tocar al Pin Pad
    Route::get('/pinpad/reverso-disponible', [PinpadController::class, 'reversoDisponible']);
    Route::post('/pinpad/maxidolar', [PinpadController::class, 'maxidolar']);

    // ----- CT — Cierre / Consulta de Tarjeta -----
    Route::post('/pinpad/cierre-turno', [PinpadController::class, 'cierreTurno']);

    // ----- LT — Lectura de Tarjeta (offline o pre-pago) -----
    Route::post('/pinpad/lectura', [PinpadController::class, 'lectura']);

    // ----- CP — Configuracion del Pin Pad (admin only) -----
    Route::post('/pinpad/cambio-parametros', [PinpadController::class, 'cambioParametros']);

    // ----- PC — Proceso de Control / Cierre de Lote -----
    Route::post('/pinpad/cierre-lote', [PinpadController::class, 'cierreLote']);

    // ----- RA — Avance en Efectivo / Cash Advance -----
    Route::post('/pinpad/reimpresion', [PinpadController::class, 'reimpresion']);

    // ============================================================
    // PIN PAD - JAR BRIDGE (Trama Builder JavaFX)
    //
    // Endpoints aparte que abren la UI oficial JavaFX en la pantalla del
    // cajero y traen la trama de vuelta via polling con archivos.
    //
    // Controlador: app/Http/Controllers/pinpadjar/PinpadJarController.php
    // ============================================================
    // GET diag    : verifica paths/archivos sin lanzar Java
    Route::get('/pinpad/jar/diag', [PinpadJarController::class, 'diag']);
    // POST abrir  : lanza el JAR en background, devuelve session_id
    Route::post('/pinpad/jar/abrir', [PinpadJarController::class, 'abrir']);
    // GET leer    : polling cada 1.5s. Devuelve pending/ready/cancelled
    Route::get('/pinpad/jar/leer/{sessionId}', [PinpadJarController::class, 'leer']);
    // DELETE limpiar : borra archivos de sesion tras consumirla
    Route::delete('/pinpad/jar/limpiar/{sessionId}', [PinpadJarController::class, 'limpiar']);


    // ---------- START SISTEMA NOVASOFT ----------
    Route::get('/n8tt-aa1v-7g0a-m2ig-b7fq-c3ar-r1nc', [MigracionController::class, 'aav_migracion_cartera']);
    Route::get('/2e62-aa1v-e3sr-m2ig-33fi-c3li-xhv3', [MigracionController::class, 'aav_migracion_cliente']);
    Route::get('/7eiq-aa1v-72iu-m2ig-2nyv-r3ef-3l4y', [MigracionController::class, 'aav_migracion_referencias_cliente']);
    Route::get('/8ert-aa1v-5tre-m2ig-3er2-c3se-98re', [MigracionController::class, 'aav_migracion_CobrosxSecretaria_PeriodoyMes_Actual']);
    Route::get('/9wet-aa1v-y9ri-m2ig-3er2-r3ut-t9yp', [MigracionController::class, 'aav_migracion_rutaje']);
    Route::get('/5r3t-aa1v-y4ws-m2ig-ser5-c3rt-oe7p', [MigracionController::class, 'aav_migracion_cartera_historica']);
    Route::get('/af_migracion_cartera_historica_api/{anio}/{mes}/{dia}', [MigracionController::class, 'af_migracion_cartera_historica_api']);
    Route::get('/af_migracion_cartera_historica_xcuotas_api/{anio}/{mes}/{dia}', [MigracionController::class, 'af_migracion_cartera_historica_xcuotas_api']);
    Route::get('/af_migracion_cartera_historica_xcuotas_xcobros_api/{anio}/{mes}/{dia}', [MigracionController::class, 'af_migracion_cartera_historica_xcuotas_xcobros_api']);
    Route::get('/imagenes_base64', [MigracionController::class, 'imagenes_base64']);
    Route::get('/aav_migracion_cliente_byIdentificacion/{identificacion}', [MigracionController::class, 'aav_migracion_cliente_byIdentificacion']);
    Route::get('/aav_acuerdos_de_pago', [MigracionController::class, 'aav_acuerdos_de_pago']);
    Route::get('/aav_migracion_productos_facturados/{factura}', [MigracionController::class, 'aav_migracion_productos_facturados']);
    Route::get('/historica_byComprobante/{factura}/{historicaInteger}', [MigracionController::class, 'historica_byComprobante']);
    Route::get('/av_producto_tipoproducto_stock', [MigracionController::class, 'av_producto_tipoproducto_stock']);
    Route::get('/aav_migracion_productos_facturados_detalle/{factura}', [MigracionController::class, 'aav_migracion_productos_facturados_detalle']);
    Route::get('/aav_migracion_cuotas_gratis', [MigracionController::class, 'aav_migracion_cuotas_gratis']);
    Route::get('/aav_migracion_rec_anulados', [MigracionController::class, 'aav_migracion_rec_anulados']);
    Route::get('/aav_migracion_referencias_cliente_by_identificacion/{identificacion}', [MigracionController::class, 'aav_migracion_referencias_cliente_by_identificacion']);
    // ---------- END SISTEMA NOVASOFT ----------



    // ---------- START SISTEMA CREDIGESTION ----------
    Route::get('/alm_facturas/{anio}/{mes}/{dia}', [CrediGestionController::class, 'alm_facturas']);
    // ---------- END SISTEMA CREDIGESTION ----------



    // ---------- START ORACLE DB Y POSTGRES ----------
    Route::get('/multinivel/{anio}/{mes}/{dia}', [MultiNivelController::class, 'multinivel']);
    Route::get('/multinivel2/{anio}/{mes}/{dia}', [MultiNivelController::class, 'multinivel2']);
    Route::get('/multinivel_nce/{anio}/{mes}/{dia}', [MultiNivelController::class, 'multinivel_nce']);

    Route::get('/listVsCelProspecto', [CelProspectoController::class, 'listVsCelProspecto']);
    // ---------- END ORACLE DB Y POSTGRES ----------
});


// ============================================================
// FILE MANAGER — gestor documental con jerarquía de carpetas,
// permisos granulares, papelera, versionado y tags.
// Controllers: app/Http/Controllers/fileManager/Fm*Controller.php
// URLs: /api/crm/file-manager/...
// ============================================================
Route::group(['prefix' => 'crm/file-manager', 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function () {

    // ----- Configuración pública del módulo (límites, extensiones bloqueadas) -----
    Route::get('/config', [FmArchivoController::class, 'config']);

    // ----- Navegación de carpetas -----
    Route::get('/roots', [FmCarpetaController::class, 'roots']);
    Route::get('/folder/tree', [FmCarpetaController::class, 'tree']);
    Route::get('/folder/{id}/contents', [FmCarpetaController::class, 'contents']);
    Route::get('/folder/{id}/breadcrumb', [FmCarpetaController::class, 'breadcrumb']);
    Route::get('/folder/{id}/all-ids', [FmCarpetaController::class, 'allIds']);

    // ----- CRUD de carpetas -----
    Route::post('/folder', [FmCarpetaController::class, 'create']);
    Route::put('/folder/{id}/rename', [FmCarpetaController::class, 'rename']);
    Route::put('/folder/{id}/move', [FmCarpetaController::class, 'move']);
    Route::post('/folder/{id}/restore', [FmCarpetaController::class, 'restore']);
    Route::get('/folder/{id}', [FmCarpetaController::class, 'show']);
    Route::delete('/folder/{id}', [FmCarpetaController::class, 'delete']);

    // ----- Archivos -----
    Route::post('/file/upload', [FmArchivoController::class, 'upload']);
    Route::post('/file/upload-folder', [FmArchivoController::class, 'uploadFolder']);
    Route::get('/file/{id}/download', [FmArchivoController::class, 'download']);
    Route::get('/file/{id}/preview', [FmArchivoController::class, 'preview']);
    Route::put('/file/{id}/rename', [FmArchivoController::class, 'rename']);
    Route::put('/file/{id}/move', [FmArchivoController::class, 'move']);
    Route::post('/file/{id}/restore', [FmArchivoController::class, 'restore']);
    Route::get('/file/{id}', [FmArchivoController::class, 'show']);
    Route::delete('/file/{id}', [FmArchivoController::class, 'delete']);

    // ----- Operaciones en lote (bulk) -----
    Route::post('/bulk-delete', [FmBulkController::class, 'bulkDelete']);
    Route::post('/bulk-move', [FmBulkController::class, 'bulkMove']);
    Route::post('/bulk-download', [FmBulkController::class, 'bulkDownload']);

    // ----- Tags -----
    Route::get('/tags', [FmTagController::class, 'index']);
    Route::post('/tags', [FmTagController::class, 'store']);
    Route::put('/tags/{id}', [FmTagController::class, 'update']);
    Route::delete('/tags/{id}', [FmTagController::class, 'delete']);
    Route::post('/file/{archivoId}/tags', [FmTagController::class, 'asignarTags']);
    Route::delete('/file/{archivoId}/tags/{tagId}', [FmTagController::class, 'quitarTag']);

    // ----- Permisos de carpeta -----
    Route::get('/folder/{id}/permisos', [FmPermisosController::class, 'indexCarpeta']);
    Route::post('/folder/{id}/permisos', [FmPermisosController::class, 'storeCarpeta']);
    Route::put('/folder/{id}/permisos/{userId}', [FmPermisosController::class, 'updateCarpeta']);
    Route::delete('/folder/{id}/permisos/{userId}', [FmPermisosController::class, 'destroyCarpeta']);

    // ----- Permisos de archivo -----
    Route::get('/file/{id}/permisos', [FmPermisosController::class, 'indexArchivo']);
    Route::post('/file/{id}/permisos', [FmPermisosController::class, 'storeArchivo']);
    Route::put('/file/{id}/permisos/{userId}', [FmPermisosController::class, 'updateArchivo']);
    Route::delete('/file/{id}/permisos/{userId}', [FmPermisosController::class, 'destroyArchivo']);

    // ----- Autocomplete de usuarios -----
    Route::get('/usuarios-asignables', [FmPermisosController::class, 'usuariosAsignables']);

    // ----- Papelera -----
    Route::get('/papelera', [FmCarpetaController::class, 'papelera']);
    Route::delete('/papelera/vaciar', [FmCarpetaController::class, 'vaciarPapelera']);
    Route::delete('/file/{id}/permanente', [FmArchivoController::class, 'deletePermanente']);
    Route::delete('/folder/{id}/permanente', [FmCarpetaController::class, 'deletePermanente']);

    // ----- Búsqueda global -----
    Route::get('/search', [FmSearchController::class, 'search']);

    // ----- Auditoría (admin) -----
    Route::get('/auditoria/recientes', [FmAuditController::class, 'recientes']);

    // ----- Upload preview / check colisiones -----
    Route::post('/file/check-colisiones', [FmArchivoController::class, 'checkColisiones']);

    // ----- Versionado -----
    Route::get('/file/{id}/versiones', [FmArchivoController::class, 'versiones']);
    Route::get('/file/{id}/versiones/{versionId}/download', [FmArchivoController::class, 'descargarVersion']);
    Route::post('/file/{id}/versiones/{versionId}/restaurar', [FmArchivoController::class, 'restaurarVersion']);
});

// CORREDORES INTERNOS (Corredor ALM): formulario de clientes multinivel dentro
// del CRM. Sin link cifrado: el corredor es el usuario JWT y tipo_corredor = 1
// va quemado en el controller.
Route::group(["prefix" => "corredores", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {
    Route::post('/verificarClienteCorredor', [CorredorClienteController::class, 'verificarClienteCorredor']);
    Route::post('/guardarClienteCorredor', [CorredorClienteController::class, 'guardarClienteCorredor']);
});

// ANUNCIOS: publicidad interna mostrada al iniciar sesion y en la campanita.
// AnunciosController es el CRUD de administracion; AnunciosUsuarioController
// es el consumo del usuario final (que le toca ver y marcar visto).
Route::group(["prefix" => "anuncios", 'middleware' => ['jwt.auth', 'usuario.activo', 'verificar.version']], function ($router) {

    // ----- Administracion -----
    Route::get('/listAllAnuncios', [AnunciosController::class, 'listAllAnuncios']);
    Route::get('/getAnuncio/{id}', [AnunciosController::class, 'getAnuncio']);
    Route::post('/addAnuncio', [AnunciosController::class, 'addAnuncio']);
    Route::post('/editAnuncio/{id}', [AnunciosController::class, 'editAnuncio']);
    Route::delete('/deleteAnuncio/{id}', [AnunciosController::class, 'deleteAnuncio']);

    // ----- Imagenes -----
    Route::post('/addImagenes/{anuncio_id}', [AnunciosController::class, 'addImagenes']);
    Route::post('/reordenarImagenes/{anuncio_id}', [AnunciosController::class, 'reordenarImagenes']);
    Route::delete('/deleteImagen/{id}', [AnunciosController::class, 'deleteImagen']);

    // ----- Consumo del usuario final -----
    Route::get('/listAnunciosUsuario', [AnunciosUsuarioController::class, 'listAnunciosUsuario']);
    Route::post('/marcarVisto/{anuncio_id}', [AnunciosUsuarioController::class, 'marcarVisto']);
    Route::post('/marcarVistosVarios', [AnunciosUsuarioController::class, 'marcarVistosVarios']);
});
