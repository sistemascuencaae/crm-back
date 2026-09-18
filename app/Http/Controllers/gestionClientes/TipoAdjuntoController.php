<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\TipoAdjunto;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoAdjuntoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todos los tipos de adjunto
     */
    public function listAllTiposAdjunto(Request $request)
    {
        $log = new Funciones();
        try {
            $tipos = TipoAdjunto::orderBy('nombre', 'asc')->get();

            $log->logInfo(TipoAdjuntoController::class, 'Se listaron los tipos de adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $tipos));
        } catch (Exception $e) {
            $log->logError(TipoAdjuntoController::class, 'Error al listar los tipos de adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar tipos de adjunto activos
     */
    public function listTiposAdjuntoActivos(Request $request)
    {
        $log = new Funciones();
        try {
            $tipos = TipoAdjunto::where('estado', true)->orderBy('nombre', 'asc')->get();

            $log->logInfo(TipoAdjuntoController::class, 'Se listaron los tipos de adjunto activos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $tipos));
        } catch (Exception $e) {
            $log->logError(TipoAdjuntoController::class, 'Error al listar los tipos de adjunto activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Crear un nuevo tipo de adjunto
     */
    public function addTipoAdjunto(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $tipo = DB::transaction(function () use ($request) {
                return TipoAdjunto::create($request->all());
            });

            $log->logInfo(TipoAdjuntoController::class, 'Se creó el tipo de adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $tipo), 201);
        } catch (Exception $e) {
            $log->logError(TipoAdjuntoController::class, 'Error al crear el tipo de adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un tipo de adjunto
     */
    public function editTipoAdjunto(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $tipo = DB::transaction(function () use ($request, $id) {
                $tipo = TipoAdjunto::findOrFail($id);
                $tipo->update($request->all());
                return $tipo;
            });

            $log->logInfo(TipoAdjuntoController::class, 'Se actualizó el tipo de adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $tipo));
        } catch (Exception $e) {
            $log->logError(TipoAdjuntoController::class, 'Error al actualizar el tipo de adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado)
     */
    public function deleteTipoAdjunto($id)
    {
        $log = new Funciones();
        try {
            $tipo = DB::transaction(function () use ($id) {
                $tipo = TipoAdjunto::findOrFail($id);
                $tipo->estado = false;
                $tipo->save();
                return $tipo;
            });

            $log->logInfo(TipoAdjuntoController::class, 'Se eliminó el tipo de adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $tipo));
        } catch (Exception $e) {
            $log->logError(TipoAdjuntoController::class, 'Error al eliminar el tipo de adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
