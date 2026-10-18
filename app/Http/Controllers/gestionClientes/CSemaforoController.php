<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\CSemaforo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CSemaforoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // * Listar todos los semáforos
    public function listAllSemaforos()
    {
        $log = new Funciones();
        try {
            $semaforos = CSemaforo::with('dsemaforos')->orderBy('nombre', 'asc')->get();
            $log->logInfo(CSemaforoController::class, 'Se listaron todos los semáforos exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $semaforos));
        } catch (Exception $e) {
            $log->logError(CSemaforoController::class, 'Error al listar los semáforos', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    // * Listar semáforos activos
    public function listSemaforosActivos()
    {
        $log = new Funciones();
        try {
            $semaforos = CSemaforo::with('dsemaforos')->where('estado', true)->orderBy('nombre', 'asc')->get();
            $log->logInfo(CSemaforoController::class, 'Se listaron los semáforos activos exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $semaforos));
        } catch (Exception $e) {
            $log->logError(CSemaforoController::class, 'Error al listar los semáforos activos', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    // * Crear un semáforo
    public function addSemaforo(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
            ]);

            $semaforo = DB::transaction(function () use ($request) {
                $semaforo = new CSemaforo();
                $semaforo->nombre = $request->nombre;
                $semaforo->estado = $request->estado ?? true;
                $semaforo->save();
                return $semaforo;
            });

            $log->logInfo(CSemaforoController::class, 'Se creó el semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $semaforo), 201);
        } catch (Exception $e) {
            $log->logError(CSemaforoController::class, 'Error al crear el semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    // * Editar un semáforo
    public function editSemaforo(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
            ]);

            $semaforo = DB::transaction(function () use ($request, $id) {
                $semaforo = CSemaforo::findOrFail($id);
                $semaforo->nombre = $request->nombre;
                if ($request->has('estado')) {
                    $semaforo->estado = $request->estado;
                }
                $semaforo->save();
                return $semaforo;
            });

            $log->logInfo(CSemaforoController::class, 'Se actualizó el semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $semaforo));
        } catch (Exception $e) {
            $log->logError(CSemaforoController::class, 'Error al actualizar el semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    // * Eliminar un semáforo (soft delete)
    public function deleteSemaforo($id)
    {
        $log = new Funciones();
        try {
            $semaforo = DB::transaction(function () use ($id) {
                $semaforo = CSemaforo::findOrFail($id);
                $semaforo->estado = false;
                $semaforo->save();
                return $semaforo;
            });

            $log->logInfo(CSemaforoController::class, 'Se eliminó el semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $semaforo));
        } catch (Exception $e) {
            $log->logError(CSemaforoController::class, 'Error al eliminar el semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
