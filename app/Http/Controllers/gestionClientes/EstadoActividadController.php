<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\EstadoActividad;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadoActividadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todos los estados
     */
    public function listAllEstados(Request $request)
    {
        $log = new Funciones();
        try {
            $estados = EstadoActividad::orderBy('nombre', 'asc')->get();

            $log->logInfo(EstadoActividadController::class, 'Se listaron los estados exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $estados));
        } catch (Exception $e) {
            $log->logError(EstadoActividadController::class, 'Error al listar los estados', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar estados activos
     */
    public function listEstadosActivos(Request $request)
    {
        $log = new Funciones();
        try {
            $estados = EstadoActividad::where('estado', true)->orderBy('nombre', 'asc')->get();

            $log->logInfo(EstadoActividadController::class, 'Se listaron los estados activos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $estados));
        } catch (Exception $e) {
            $log->logError(EstadoActividadController::class, 'Error al listar los estados activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Crear un nuevo estado
     */
    public function addEstado(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $estadoActividad = DB::transaction(function () use ($request) {
                return EstadoActividad::create($request->all());
            });

            $log->logInfo(EstadoActividadController::class, 'Se creó el estado exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $estadoActividad), 201);
        } catch (Exception $e) {
            $log->logError(EstadoActividadController::class, 'Error al crear el estado', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un estado
     */
    public function editEstado(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $estadoActividad = DB::transaction(function () use ($request, $id) {
                $estadoActividad = EstadoActividad::findOrFail($id);
                $estadoActividad->update($request->all());
                return $estadoActividad;
            });

            $log->logInfo(EstadoActividadController::class, 'Se actualizó el estado exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $estadoActividad));
        } catch (Exception $e) {
            $log->logError(EstadoActividadController::class, 'Error al actualizar el estado', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado)
     */
    public function deleteEstado($id)
    {
        $log = new Funciones();
        try {
            $estadoActividad = DB::transaction(function () use ($id) {
                $estadoActividad = EstadoActividad::findOrFail($id);
                $estadoActividad->estado = false;
                $estadoActividad->save();
                return $estadoActividad;
            });

            $log->logInfo(EstadoActividadController::class, 'Se eliminó el estado exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $estadoActividad));
        } catch (Exception $e) {
            $log->logError(EstadoActividadController::class, 'Error al eliminar el estado', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
