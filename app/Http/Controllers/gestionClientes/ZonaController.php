<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\Zona;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZonaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todas las zonas
     */
    public function listAllZonas(Request $request)
    {
        $log = new Funciones();
        try {
            $zonas = Zona::orderBy('nombre', 'asc')->get();

            $log->logInfo(ZonaController::class, 'Se listaron todas las zonas exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $zonas));
        } catch (Exception $e) {
            $log->logError(ZonaController::class, 'Error al listar las zonas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar zonas activas
     */
    public function listZonasActivas(Request $request)
    {
        $log = new Funciones();
        try {
            $zonas = Zona::where('estado', true)->orderBy('nombre', 'asc')->get();

            $log->logInfo(ZonaController::class, 'Se listaron las zonas activas exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $zonas));
        } catch (Exception $e) {
            $log->logError(ZonaController::class, 'Error al listar las zonas activas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener una zona por ID
     */
    public function getZonaById($id)
    {
        $log = new Funciones();
        try {
            $zona = Zona::with('clientes')->findOrFail($id);

            $log->logInfo(ZonaController::class, 'Se obtuvo la zona exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $zona));
        } catch (Exception $e) {
            $log->logError(ZonaController::class, 'Error al obtener la zona', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }

    /**
     * Crear una nueva zona
     */
    public function addZona(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'codigo' => 'required|unique:crm.zona,codigo',
                'nombre' => 'required|string|max:255'
            ]);

            $zona = DB::transaction(function () use ($request) {
                $zona = new Zona();
                $zona->codigo = $request->codigo;
                $zona->nombre = $request->nombre;
                $zona->estado = $request->estado ?? true;
                $zona->save();

                return $zona;
            });

            $log->logInfo(ZonaController::class, 'Se creó la zona exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $zona), 201);
        } catch (Exception $e) {
            $log->logError(ZonaController::class, 'Error al crear la zona', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar una zona existente
     */
    public function editZona(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'codigo' => 'required|unique:crm.zona,codigo,' . $id,
                'nombre' => 'required|string|max:255'
            ]);

            $zona = DB::transaction(function () use ($request, $id) {
                $zona = Zona::findOrFail($id);
                $zona->codigo = $request->codigo;
                $zona->nombre = $request->nombre;
                if ($request->has('estado')) {
                    $zona->estado = $request->estado;
                }
                $zona->save();

                return $zona;
            });

            $log->logInfo(ZonaController::class, 'Se actualizó la zona exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $zona));
        } catch (Exception $e) {
            $log->logError(ZonaController::class, 'Error al actualizar la zona', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado) de una zona
     */
    public function deleteZona($id)
    {
        $log = new Funciones();
        try {
            $zona = DB::transaction(function () use ($id) {
                $zona = Zona::findOrFail($id);
                $zona->estado = false;
                $zona->save();

                return $zona;
            });

            $log->logInfo(ZonaController::class, 'Se eliminó la zona exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $zona));
        } catch (Exception $e) {
            $log->logError(ZonaController::class, 'Error al eliminar la zona', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
