<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Estados;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listEstadosActivoByTablero($id)
    {
        $log = new Funciones();
        try {
            $estado = Estados::where('tab_id', $id)->where('estado', true)->get();

            $log->logInfo(EstadosController::class, 'Se listo con exito los estados activos del tablero con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $estado));
        } catch (Exception $e) {
            $log->logError(EstadosController::class, 'Error al listar los estados activos del tablero con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listEstadosByTablero($id)
    {
        $log = new Funciones();
        try {
            $estado = Estados::where('tab_id', $id)->get();

            $log->logInfo(EstadosController::class, 'Se listo con exito los estados del tablero con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $estado));
        } catch (Exception $e) {
            $log->logError(EstadosController::class, 'Error al listar los estados del tablero con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addEstado(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {
                $estado = Estados::create($request->all());

                return Estados::where('tab_id', $estado->tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            });

            $log->logInfo(EstadosController::class, 'Se guardo con exito el estado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EstadosController::class, 'Error al guardar el estado', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEstado(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $estado = Estados::findOrFail($id);

                $estado->update($request->all());

                return Estados::where('id', $estado->id)->first();
            });

            $log->logInfo(EstadosController::class, 'Se actualizo con exito el estado con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EstadosController::class, 'Error al actualizar el estado con el Id: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteEstado($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id) {
                $estado = Estados::findOrFail($id);

                $estado->delete();

                return $estado;
            });

            $log->logInfo(EstadosController::class, 'Se elimino con exito el estado con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EstadosController::class, 'Error al eliminar el estado con el Id: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}