<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\ActividadCliente;
use App\Models\gestionClientes\BitacoraGestionCliente;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActividadClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todas las actividades
     */
    public function listAllActividades(Request $request)
    {
        $log = new Funciones();
        try {
            $query = ActividadCliente::with([
                'cliente',
                'usuario',
                'tipoActividad',
                'prioridadActividad',
                'estadoActividad',
                'etiquetas'
            ]);

            // Filtro por cliente
            if ($request->has('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            // Filtro por usuario
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filtro por estado de actividad
            if ($request->has('estado_actividad_id')) {
                $query->where('estado_actividad_id', $request->estado_actividad_id);
            }

            // Filtro por tipo de actividad
            if ($request->has('tipo_actividad_id')) {
                $query->where('tipo_actividad_id', $request->tipo_actividad_id);
            }

            // Filtro por rango de fechas
            if ($request->has('fecha_desde')) {
                $query->whereDate('fecha_inicio', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->whereDate('fecha_inicio', '<=', $request->fecha_hasta);
            }

            $actividades = $query->orderBy('fecha_inicio', 'desc')->get();

            $log->logInfo(ActividadClienteController::class, 'Se listaron las actividades exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al listar las actividades', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar actividades por cliente
     */
    public function listActividadesByCliente($clienteId)
    {
        $log = new Funciones();
        try {
            $actividades = ActividadCliente::with([
                'usuario',
                'tipoActividad',
                'prioridadActividad',
                'estadoActividad',
                'etiquetas'
            ])
                ->where('cliente_id', $clienteId)
                ->orderBy('fecha_inicio', 'desc')
                ->get();

            $log->logInfo(ActividadClienteController::class, 'Se listaron las actividades del cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al listar las actividades del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar actividades pendientes por cliente
     */
    public function listActividadesPendientesByCliente($clienteId)
    {
        $log = new Funciones();
        try {
            $actividades = ActividadCliente::with([
                'usuario',
                'tipoActividad',
                'prioridadActividad',
                'estadoActividad',
                'etiquetas'
            ])
                ->where('cliente_id', $clienteId)
                ->whereNull('fecha_cierre')
                ->orderBy('fecha_inicio', 'desc')
                ->get();

            $log->logInfo(ActividadClienteController::class, 'Se listaron las actividades pendientes del cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al listar las actividades pendientes del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar actividades terminadas por cliente
     */
    public function listActividadesTerminadasByCliente($clienteId)
    {
        $log = new Funciones();
        try {
            $actividades = ActividadCliente::with([
                'usuario',
                'tipoActividad',
                'prioridadActividad',
                'estadoActividad',
                'etiquetas'
            ])
                ->where('cliente_id', $clienteId)
                ->whereNotNull('fecha_cierre')
                ->orderBy('fecha_cierre', 'desc')
                ->get();

            $log->logInfo(ActividadClienteController::class, 'Se listaron las actividades terminadas del cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al listar las actividades terminadas del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener una actividad por ID
     */
    public function getActividadById($id)
    {
        $log = new Funciones();
        try {
            $actividad = ActividadCliente::with([
                'cliente',
                'usuario',
                'tipoActividad',
                'prioridadActividad',
                'estadoActividad',
                'etiquetas',
                'adjuntos.tipoAdjunto'
            ])->findOrFail($id);

            $log->logInfo(ActividadClienteController::class, 'Se obtuvo la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $actividad));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al obtener la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }

    /**
     * Crear una nueva actividad
     */
    public function addActividad(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'cliente_id' => 'required|integer',
                'user_id' => 'required|integer',
                'tipo_actividad_id' => 'required|integer',
                'prioridad_actividad_id' => 'required|integer',
                'estado_actividad_id' => 'required|integer',
                'fecha_inicio' => 'required|date',
                'hora_inicio' => 'nullable|date_format:H:i',
                'observacion_inicio' => 'nullable|string',
                'valor' => 'nullable|numeric',
                'es_reasignado' => 'nullable|boolean'
            ]);

            $actividad = DB::transaction(function () use ($request) {
                $actividad = ActividadCliente::create($request->all());

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'addActividad',
                    'valores_anteriores' => [],
                    'valores_nuevos' => $actividad->toArray(),
                    'actividad_cliente_id' => $actividad->id,
                ]);

                return $actividad->load([
                    'cliente',
                    'usuario',
                    'tipoActividad',
                    'prioridadActividad',
                    'estadoActividad'
                ]);
            });

            $log->logInfo(ActividadClienteController::class, 'Se creó la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $actividad));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al crear la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()));
        }
    }

    /**
     * Actualizar una actividad existente
     */
    public function editActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'tipo_actividad_id' => 'required|integer',
                'prioridad_actividad_id' => 'required|integer',
                'estado_actividad_id' => 'required|integer',
                'fecha_inicio' => 'required|date',
                'valor' => 'nullable|numeric'
            ]);

            $actividad = DB::transaction(function () use ($request, $id) {
                $actividad = ActividadCliente::findOrFail($id);
                $valoresAnteriores = $actividad->toArray();

                $actividad->update($request->all());

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'editActividad',
                    'valores_anteriores' => $valoresAnteriores,
                    'valores_nuevos' => $actividad->fresh()->toArray(),
                    'actividad_cliente_id' => $actividad->id,
                ]);

                return $actividad->load([
                    'cliente',
                    'usuario',
                    'tipoActividad',
                    'prioridadActividad',
                    'estadoActividad',
                    'etiquetas'
                ]);
            });

            $log->logInfo(ActividadClienteController::class, 'Se actualizó la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $actividad));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al actualizar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Cerrar una actividad
     */
    public function cerrarActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'estado_actividad_id' => 'required|integer',
                'fecha_cierre' => 'required|date',
                'hora_cierre' => 'nullable|date_format:H:i',
                'observacion_cierre' => 'required|string'
            ]);

            $actividad = DB::transaction(function () use ($request, $id) {
                $actividad = ActividadCliente::findOrFail($id);
                $valoresAnteriores = $actividad->toArray();

                $actividad->estado_actividad_id = $request->estado_actividad_id;
                $actividad->fecha_cierre = $request->fecha_cierre;
                $actividad->hora_cierre = $request->hora_cierre;
                $actividad->observacion_cierre = $request->observacion_cierre;
                $actividad->save();

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'cerrarActividad',
                    'valores_anteriores' => $valoresAnteriores,
                    'valores_nuevos' => $actividad->toArray(),
                    'actividad_cliente_id' => $actividad->id,
                ]);

                return $actividad->load([
                    'cliente',
                    'usuario',
                    'tipoActividad',
                    'estadoActividad'
                ]);
            });

            $log->logInfo(ActividadClienteController::class, 'Se cerró la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se cerró con éxito', $actividad));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al cerrar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al cerrar', $e->getMessage()), 500);
        }
    }

    /**
     * Reasignar una actividad a otro usuario
     */
    public function reasignarActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'user_id' => 'required|integer'
            ]);

            $actividad = DB::transaction(function () use ($request, $id) {
                $actividad = ActividadCliente::findOrFail($id);
                $valoresAnteriores = $actividad->toArray();

                $actividad->user_id = $request->user_id;
                $actividad->es_reasignado = true;
                $actividad->save();

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'reasignarActividad',
                    'valores_anteriores' => $valoresAnteriores,
                    'valores_nuevos' => $actividad->toArray(),
                    'actividad_cliente_id' => $actividad->id,
                ]);

                return $actividad->load([
                    'cliente',
                    'usuario',
                    'tipoActividad',
                    'estadoActividad'
                ]);
            });

            $log->logInfo(ActividadClienteController::class, 'Se reasignó la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se reasignó con éxito', $actividad));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al reasignar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al reasignar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar una actividad
     */
    public function deleteActividad($id)
    {
        $log = new Funciones();
        try {
            // Obtener parámetro de configuración NAS
            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

            $actividad = DB::transaction(function () use ($id, $parametro, $log) {
                $actividad = ActividadCliente::findOrFail($id);

                // Obtener todos los adjuntos de la actividad
                $adjuntos = DB::table('crm.adjuntos_cliente')
                    ->where('actividad_cliente_id', $id)
                    ->get();

                // Obtener todos los adjuntos de la actividad
                $etiquetas = DB::table('crm.etiquetas_actividad')
                    ->where('actividad_cliente_id', $id)
                    ->get();

                // Eliminar archivos físicos y registros de adjuntos
                foreach ($adjuntos as $adjunto) {
                    if ($adjunto->url) {
                        try {
                            if ($parametro->nas == true) {
                                Storage::disk('nas')->delete($adjunto->url);
                            } else {
                                Storage::disk('local')->delete($adjunto->url);
                            }
                        } catch (Exception $e) {
                            // Continuar aunque falle la eliminación del archivo
                            $log->logError(ActividadClienteController::class, 'Error al eliminar archivo adjunto: ' . $adjunto->url, $e);
                        }
                    }

                    // Eliminar registro del adjunto
                    DB::table('crm.adjuntos_cliente')->where('id', $adjunto->id)->delete();
                }

                // Eliminar archivos físicos y registros de adjuntos
                foreach ($etiquetas as $etiqueta) {
                    // Eliminar registro del adjunto
                    DB::table('crm.etiquetas_actividad')->where('id', $etiqueta->id)->delete();
                }

                // Eliminar la actividad
                $actividad->delete();

                return $actividad;
            });

            $log->logInfo(ActividadClienteController::class, 'Se eliminó la actividad y sus adjuntos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $actividad));
        } catch (Exception $e) {
            $log->logError(ActividadClienteController::class, 'Error al eliminar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
