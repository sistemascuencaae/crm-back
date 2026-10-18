<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\EtiquetaActividad;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EtiquetaActividadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar etiquetas de una actividad
     */
    public function listEtiquetasByActividad($actividadId)
    {
        $log = new Funciones();
        try {
            $etiquetas = EtiquetaActividad::where('actividad_cliente_id', $actividadId)
                ->orderBy('created_at', 'desc')
                ->get();

            $log->logInfo(EtiquetaActividadController::class, 'Se listaron las etiquetas exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $etiquetas));
        } catch (Exception $e) {
            $log->logError(EtiquetaActividadController::class, 'Error al listar las etiquetas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Crear una nueva etiqueta
     */
    public function addEtiquetaActividad(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'actividad_cliente_id' => 'required|integer',
                'nombre' => 'required|string|max:255'
            ]);

            $etiqueta = DB::transaction(function () use ($request) {
                $etiqueta = EtiquetaActividad::create($request->all());
                return $etiqueta->load('actividad');
            });

            $log->logInfo(EtiquetaActividadController::class, 'Se creó la etiqueta exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $etiqueta), 201);
        } catch (Exception $e) {
            $log->logError(EtiquetaActividadController::class, 'Error al crear la etiqueta', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar una etiqueta
     */
    public function editEtiquetaActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255'
            ]);

            $etiqueta = DB::transaction(function () use ($request, $id) {
                $etiqueta = EtiquetaActividad::findOrFail($id);
                $etiqueta->nombre = $request->nombre;
                $etiqueta->save();
                return $etiqueta->load('actividad');
            });

            $log->logInfo(EtiquetaActividadController::class, 'Se actualizó la etiqueta exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $etiqueta));
        } catch (Exception $e) {
            $log->logError(EtiquetaActividadController::class, 'Error al actualizar la etiqueta', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar una etiqueta
     */
    public function deleteEtiquetaActividad($id)
    {
        $log = new Funciones();
        try {
            $etiqueta = DB::transaction(function () use ($id) {
                $etiqueta = EtiquetaActividad::findOrFail($id);
                $etiqueta->delete();
                return $etiqueta;
            });

            $log->logInfo(EtiquetaActividadController::class, 'Se eliminó la etiqueta exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $etiqueta));
        } catch (Exception $e) {
            $log->logError(EtiquetaActividadController::class, 'Error al eliminar la etiqueta', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
