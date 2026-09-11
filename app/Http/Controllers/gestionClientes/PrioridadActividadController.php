<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\PrioridadActividad;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrioridadActividadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todas las prioridades
     */
    public function listAllPrioridades(Request $request)
    {
        $log = new Funciones();
        try {
            $prioridades = PrioridadActividad::orderBy('nombre', 'asc')->get();

            $log->logInfo(PrioridadActividadController::class, 'Se listaron las prioridades exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $prioridades));
        } catch (Exception $e) {
            $log->logError(PrioridadActividadController::class, 'Error al listar las prioridades', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar prioridades activas
     */
    public function listPrioridadesActivas(Request $request)
    {
        $log = new Funciones();
        try {
            $prioridades = PrioridadActividad::where('estado', true)->orderBy('nombre', 'asc')->get();

            $log->logInfo(PrioridadActividadController::class, 'Se listaron las prioridades activas exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $prioridades));
        } catch (Exception $e) {
            $log->logError(PrioridadActividadController::class, 'Error al listar las prioridades activas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Crear una nueva prioridad
     */
    public function addPrioridad(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $prioridad = DB::transaction(function () use ($request) {
                return PrioridadActividad::create($request->all());
            });

            $log->logInfo(PrioridadActividadController::class, 'Se creó la prioridad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $prioridad), 201);
        } catch (Exception $e) {
            $log->logError(PrioridadActividadController::class, 'Error al crear la prioridad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar una prioridad
     */
    public function editPrioridad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $prioridad = DB::transaction(function () use ($request, $id) {
                $prioridad = PrioridadActividad::findOrFail($id);
                $prioridad->update($request->all());
                return $prioridad;
            });

            $log->logInfo(PrioridadActividadController::class, 'Se actualizó la prioridad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $prioridad));
        } catch (Exception $e) {
            $log->logError(PrioridadActividadController::class, 'Error al actualizar la prioridad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado)
     */
    public function deletePrioridad($id)
    {
        $log = new Funciones();
        try {
            $prioridad = DB::transaction(function () use ($id) {
                $prioridad = PrioridadActividad::findOrFail($id);
                $prioridad->estado = false;
                $prioridad->save();
                return $prioridad;
            });

            $log->logInfo(PrioridadActividadController::class, 'Se eliminó la prioridad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $prioridad));
        } catch (Exception $e) {
            $log->logError(PrioridadActividadController::class, 'Error al eliminar la prioridad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
