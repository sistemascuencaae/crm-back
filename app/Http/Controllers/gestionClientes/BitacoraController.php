<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\BitacoraGestionCliente;
use Exception;
use Illuminate\Http\Request;

class BitacoraController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar registros de bitácora
     */
    public function listBitacora(Request $request)
    {
        $log = new Funciones();
        try {
            $query = BitacoraGestionCliente::with('usuario');

            // Filtro por usuario
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filtro por acción
            if ($request->has('accion')) {
                $query->where('accion', $request->accion);
            }

            // Filtro por rango de fechas
            if ($request->has('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            $bitacora = $query->orderBy('created_at', 'desc')->paginate(50);

            $log->logInfo(BitacoraController::class, 'Se listó la bitácora exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $bitacora));
        } catch (Exception $e) {
            $log->logError(BitacoraController::class, 'Error al listar la bitácora', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener un registro de bitácora por ID
     */
    public function getBitacoraById($id)
    {
        $log = new Funciones();
        try {
            $bitacora = BitacoraGestionCliente::with('usuario')->findOrFail($id);

            $log->logInfo(BitacoraController::class, 'Se obtuvo el registro de bitácora exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $bitacora));
        } catch (Exception $e) {
            $log->logError(BitacoraController::class, 'Error al obtener el registro de bitácora', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener bitácora por ID de actividad de cliente
     */
    public function getBitacoraByActividadClienteId($actividadClienteId)
    {
        $log = new Funciones();
        try {
            // Buscar en valores_anteriores y valores_nuevos donde aparezca el ID de la actividad
            $bitacora = BitacoraGestionCliente::with('usuario')
                ->where(function($query) use ($actividadClienteId) {
                    $query->where('valores_anteriores->id', $actividadClienteId)
                          ->orWhere('valores_nuevos->id', $actividadClienteId);
                })
                ->orderBy('created_at', 'asc')
                ->get();

            $log->logInfo(BitacoraController::class, 'Se obtuvo el registro de bitácora de la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $bitacora));
        } catch (Exception $e) {
            $log->logError(BitacoraController::class, 'Error al obtener el registro de bitácora de la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }
}
