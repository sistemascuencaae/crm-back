<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\Cliente;
use App\Models\gestionClientes\BitacoraGestionCliente;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todos los clientes
     */
    public function listAllClientes(Request $request)
    {
        $log = new Funciones();
        try {
            $query = Cliente::with(['zona', 'contactos', 'usuarios']);

            // Filtro por zona
            if ($request->has('zona_id')) {
                $query->where('zona_id', $request->zona_id);
            }

            // Búsqueda por identificación o nombres
            if ($request->has('buscar')) {
                $buscar = $request->buscar;
                $query->where(function ($q) use ($buscar) {
                    $q->where('identificacion', 'ILIKE', "%$buscar%")
                        ->orWhere('nombres', 'ILIKE', "%$buscar%")
                        ->orWhere('apellidos', 'ILIKE', "%$buscar%");
                });
            }

            $clientes = $query->orderBy('created_at', 'desc')->get();

            $log->logInfo(ClienteController::class, 'Se listaron los clientes exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $clientes));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al listar los clientes', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar clientes activos
     */
    public function listClientesActivos(Request $request)
    {
        $log = new Funciones();
        try {
            $query = Cliente::with(['zona', 'contactos', 'usuarios'])
                ->where('estado', true);

            if ($request->has('zona_id')) {
                $query->where('zona_id', $request->zona_id);
            }

            $clientes = $query->orderBy('created_at', 'desc')->get();

            $log->logInfo(ClienteController::class, 'Se listaron los clientes activos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $clientes));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al listar los clientes activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar clientes activos asignados al usuario
     */
    public function listClientesByUsuario()
    {
        try {
            $userId = auth()->id();

            $query = Cliente::where('estado', true)
                ->whereHas('usuarios', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });

            $clientes = $query->orderBy('nombres', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $clientes));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener un cliente por ID
     */
    public function getClienteById($id)
    {
        $log = new Funciones();
        try {
            $cliente = Cliente::with(['zona', 'contactos', 'usuarios', 'actividades.tipoActividad', 'actividades.estadoActividad', 'adjuntos.tipoAdjunto'])
                ->findOrFail($id);

            $log->logInfo(ClienteController::class, 'Se obtuvo el cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $cliente));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al obtener el cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }

    /**
     * Crear un nuevo cliente
     */
    public function addCliente(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'zona_id' => 'required|integer',
                'identificacion' => 'required|string|max:255',
                'tipo_identificacion' => 'required|in:1,2,3',
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'nullable|email',
                'telefono_1' => 'nullable|string',
                'telefono_2' => 'nullable|string',
                'telefono_3' => 'nullable|string'
            ]);

            $cliente = DB::transaction(function () use ($request) {
                $cliente = Cliente::create($request->all());

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'addCliente',
                    'valores_anteriores' => [],
                    'valores_nuevos' => $cliente->toArray()
                ]);

                return $cliente->load(['zona', 'contactos']);
            });

            $log->logInfo(ClienteController::class, 'Se creó el cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $cliente), 201);
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al crear el cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un cliente existente
     */
    public function editCliente(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'zona_id' => 'required|integer',
                'identificacion' => 'required|string|max:255',
                'tipo_identificacion' => 'required|in:1,2,3',
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'nullable|email'
            ]);

            $cliente = DB::transaction(function () use ($request, $id) {
                $cliente = Cliente::findOrFail($id);
                $valoresAnteriores = $cliente->toArray();

                $cliente->update($request->all());

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'editCliente',
                    'valores_anteriores' => $valoresAnteriores,
                    'valores_nuevos' => $cliente->fresh()->toArray()
                ]);

                return $cliente->load(['zona', 'contactos']);
            });

            $log->logInfo(ClienteController::class, 'Se actualizó el cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $cliente));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al actualizar el cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado) de un cliente
     */
    public function deleteCliente($id)
    {
        $log = new Funciones();
        try {
            $cliente = DB::transaction(function () use ($id) {
                $cliente = Cliente::findOrFail($id);
                $valoresAnteriores = $cliente->toArray();

                $cliente->estado = false;
                $cliente->save();

                // Registrar en bitácora
                BitacoraGestionCliente::create([
                    'user_id' => auth()->id(),
                    'accion' => 'deleteCliente',
                    'valores_anteriores' => $valoresAnteriores,
                    'valores_nuevos' => $cliente->toArray()
                ]);

                return $cliente;
            });

            $log->logInfo(ClienteController::class, 'Se eliminó el cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $cliente));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al eliminar el cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }

    /**
     * Asignar usuario a cliente
     */
    public function asignarUsuarioCliente(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'user_id' => 'required|integer'
            ]);

            $cliente = DB::transaction(function () use ($request, $id) {
                $cliente = Cliente::findOrFail($id);

                // Verificar si ya está asignado
                if (!$cliente->usuarios()->where('user_id', $request->user_id)->exists()) {
                    $cliente->usuarios()->attach($request->user_id);
                }

                return $cliente->load('usuarios');
            });

            $log->logInfo(ClienteController::class, 'Se asignó el usuario al cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se asignó con éxito', $cliente));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al asignar usuario', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al asignar', $e->getMessage()), 500);
        }
    }

    /**
     * Desasignar usuario de cliente
     */
    public function desasignarUsuarioCliente(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'user_id' => 'required|integer'
            ]);

            $cliente = DB::transaction(function () use ($request, $id) {
                $cliente = Cliente::findOrFail($id);
                $cliente->usuarios()->detach($request->user_id);

                return $cliente->load('usuarios');
            });

            $log->logInfo(ClienteController::class, 'Se desasignó el usuario del cliente exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se desasignó con éxito', $cliente));
        } catch (Exception $e) {
            $log->logError(ClienteController::class, 'Error al desasignar usuario', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al desasignar', $e->getMessage()), 500);
        }
    }
}
