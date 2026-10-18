<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\TipoActividad;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoActividadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todos los tipos de actividad
     */
    public function listAllTiposActividad(Request $request)
    {
        $log = new Funciones();
        try {
            $tipos = TipoActividad::orderBy('nombre', 'asc')->get();

            $log->logInfo(TipoActividadController::class, 'Se listaron los tipos de actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $tipos));
        } catch (Exception $e) {
            $log->logError(TipoActividadController::class, 'Error al listar los tipos de actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar tipos de actividad activos
     */
    public function listTiposActividadActivos(Request $request)
    {
        $log = new Funciones();
        try {
            $tipos = TipoActividad::where('estado', true)->orderBy('nombre', 'asc')->get();

            $log->logInfo(TipoActividadController::class, 'Se listaron los tipos de actividad activos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $tipos));
        } catch (Exception $e) {
            $log->logError(TipoActividadController::class, 'Error al listar los tipos de actividad activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Crear un nuevo tipo de actividad
     */
    public function addTipoActividad(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $tipo = DB::transaction(function () use ($request) {
                return TipoActividad::create($request->all());
            });

            $log->logInfo(TipoActividadController::class, 'Se creó el tipo de actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $tipo), 201);
        } catch (Exception $e) {
            $log->logError(TipoActividadController::class, 'Error al crear el tipo de actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un tipo de actividad
     */
    public function editTipoActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $tipo = DB::transaction(function () use ($request, $id) {
                $tipo = TipoActividad::findOrFail($id);
                $tipo->update($request->all());
                return $tipo;
            });

            $log->logInfo(TipoActividadController::class, 'Se actualizó el tipo de actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $tipo));
        } catch (Exception $e) {
            $log->logError(TipoActividadController::class, 'Error al actualizar el tipo de actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado)
     */
    public function deleteTipoActividad($id)
    {
        $log = new Funciones();
        try {
            $tipo = DB::transaction(function () use ($id) {
                $tipo = TipoActividad::findOrFail($id);
                $tipo->estado = false;
                $tipo->save();
                return $tipo;
            });

            $log->logInfo(TipoActividadController::class, 'Se eliminó el tipo de actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $tipo));
        } catch (Exception $e) {
            $log->logError(TipoActividadController::class, 'Error al eliminar el tipo de actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
