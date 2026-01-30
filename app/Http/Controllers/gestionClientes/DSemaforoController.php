<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\DSemaforo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DSemaforoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // * Listar rangos por semáforo
    public function listDSemaforosByCSemaforo($csemaforoId)
    {
        $log = new Funciones();
        try {
            $dsemaforos = DSemaforo::where('csemaforo_id', $csemaforoId)->orderBy('inicio', 'asc')->get();
            $log->logInfo(DSemaforoController::class, 'Se listaron los rangos del semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $dsemaforos));
        } catch (Exception $e) {
            $log->logError(DSemaforoController::class, 'Error al listar los rangos del semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    // * Crear un rango de semáforo
    public function addDSemaforo(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'csemaforo_id' => 'required|integer',
                'color' => 'required|string|max:50',
                'etiqueta' => 'nullable|string|max:255',
                'inicio' => 'required|numeric',
                'fin' => 'required|numeric',
            ]);

            $dsemaforo = DB::transaction(function () use ($request) {
                $dsemaforo = new DSemaforo();
                $dsemaforo->csemaforo_id = $request->csemaforo_id;
                $dsemaforo->color = $request->color;
                $dsemaforo->etiqueta = $request->etiqueta;
                $dsemaforo->inicio = $request->inicio;
                $dsemaforo->fin = $request->fin;
                $dsemaforo->estado = $request->estado ?? true;
                $dsemaforo->save();
                return $dsemaforo;
            });

            $log->logInfo(DSemaforoController::class, 'Se creó el rango del semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $dsemaforo), 201);
        } catch (Exception $e) {
            $log->logError(DSemaforoController::class, 'Error al crear el rango del semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    // * Editar un rango de semáforo
    public function editDSemaforo(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'color' => 'required|string|max:50',
                'etiqueta' => 'nullable|string|max:255',
                'inicio' => 'required|numeric',
                'fin' => 'required|numeric',
            ]);

            $dsemaforo = DB::transaction(function () use ($request, $id) {
                $dsemaforo = DSemaforo::findOrFail($id);
                $dsemaforo->color = $request->color;
                $dsemaforo->etiqueta = $request->etiqueta;
                $dsemaforo->inicio = $request->inicio;
                $dsemaforo->fin = $request->fin;
                if ($request->has('estado')) {
                    $dsemaforo->estado = $request->estado;
                }
                $dsemaforo->save();
                return $dsemaforo;
            });

            $log->logInfo(DSemaforoController::class, 'Se actualizó el rango del semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $dsemaforo));
        } catch (Exception $e) {
            $log->logError(DSemaforoController::class, 'Error al actualizar el rango del semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    // * Eliminar un rango de semáforo (soft delete)
    public function deleteDSemaforo($id)
    {
        $log = new Funciones();
        try {
            $dsemaforo = DB::transaction(function () use ($id) {
                $dsemaforo = DSemaforo::findOrFail($id);
                $dsemaforo->estado = false;
                $dsemaforo->save();
                return $dsemaforo;
            });

            $log->logInfo(DSemaforoController::class, 'Se eliminó el rango del semáforo exitosamente');
            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $dsemaforo));
        } catch (Exception $e) {
            $log->logError(DSemaforoController::class, 'Error al eliminar el rango del semáforo', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
