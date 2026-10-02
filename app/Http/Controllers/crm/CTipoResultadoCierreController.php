<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CTipoResultadoCierre;
use Exception;
use Illuminate\Http\Request;

class CTipoResultadoCierreController extends Controller
{
    public function addCTipoResultadoCierre(Request $request)
    {
        $log = new Funciones();

        try {
            $ctra = CTipoResultadoCierre::create($request->all());

            $resultado = CTipoResultadoCierre::where('tab_id', $ctra->tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            $log->logInfo(CTipoResultadoCierreController::class, 'Se guardo con exito el tipo de resultado de cierre');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));

        } catch (Exception $e) {
            $log->logError(CTipoResultadoCierreController::class, 'Error al guardar el tipo de resultado de cierre', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoResultadoCierreByIdTablero($tab_id)
    {
        $log = new Funciones();

        try {
            $resultado = CTipoResultadoCierre::where('tab_id', $tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            $log->logInfo(CTipoResultadoCierreController::class, 'Se listo con exito los tipos de resultado de cierre del tablero, con el ID: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            $log->logError(CTipoResultadoCierreController::class, 'Error al listar los tipos de resultado de cierre del tablero, con el ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoResultadoCierreByIdTableroEstadoActivo($tab_id)
    {
        $log = new Funciones();

        try {
            $resultado = CTipoResultadoCierre::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            $log->logInfo(CTipoResultadoCierreController::class, 'Se listo con exito los tipos de resultado de cierre con estado Activo del tablero: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            $log->logError(CTipoResultadoCierreController::class, 'Error al listar los tipos de resultado de cierre con estado Activo del tablero: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editCTipoResultadoCierre(Request $request, $id)
    {
        $log = new Funciones();

        try {
            $resultado = CTipoResultadoCierre::findOrFail($id);

            $resultado->update($request->all());

            $log->logInfo(CTipoResultadoCierreController::class, 'Se actualizo con exito el tipo de resultado de cierre, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
        } catch (Exception $e) {
            $log->logError(CTipoResultadoCierreController::class, 'Error al actualizar el tipo de resultado de cierre, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteCTipoResultadoCierre($id)
    {
        $log = new Funciones();

        try {
            $resultado = CTipoResultadoCierre::findOrFail($id);

            $resultado->delete();

            $log->logInfo(CTipoResultadoCierreController::class, 'Se elimino con exito el tipo de resultado de cierre, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $resultado));
        } catch (Exception $e) {
            $log->logError(CTipoResultadoCierreController::class, 'Error al eliminar el tipo de resultado de cierre, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listResultadoIniciadoByTableroId($tab_id)
    {
        $log = new Funciones();

        try {
            $resultadoIniciado = CTipoResultadoCierre::where('tab_id', $tab_id)->where('nombre', 'Iniciado')->first();

            $log->logInfo(CTipoResultadoCierreController::class, 'Se listo con exito los tipos de resultado INICIADOS del tablero con ID: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultadoIniciado));
        } catch (Exception $e) {
            $log->logError(CTipoResultadoCierreController::class, 'Error al listar los tipos de resultado INICIADOS del tablero con ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}