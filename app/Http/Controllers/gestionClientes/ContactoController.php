<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\Contacto;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar todos los contactos de un cliente
     */
    public function listContactosByCliente($clienteId)
    {
        $log = new Funciones();
        try {
            $contactos = Contacto::with(['parentesco', 'cargo'])
                ->where('cliente_id', $clienteId)
                ->orderBy('created_at', 'desc')
                ->get();

            $log->logInfo(ContactoController::class, 'Se listaron los contactos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $contactos));
        } catch (Exception $e) {
            $log->logError(ContactoController::class, 'Error al listar los contactos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar contactos activos de un cliente
     */
    public function listContactosActivosByCliente($clienteId)
    {
        $log = new Funciones();
        try {
            $contactos = Contacto::with(['parentesco', 'cargo'])
                ->where('cliente_id', $clienteId)
                ->where('estado', true)
                ->orderBy('created_at', 'desc')
                ->get();

            $log->logInfo(ContactoController::class, 'Se listaron los contactos activos exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $contactos));
        } catch (Exception $e) {
            $log->logError(ContactoController::class, 'Error al listar los contactos activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener un contacto por ID
     */
    public function getContactoById($id)
    {
        $log = new Funciones();
        try {
            $contacto = Contacto::with(['cliente', 'parentesco', 'cargo'])->findOrFail($id);

            $log->logInfo(ContactoController::class, 'Se obtuvo el contacto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $contacto));
        } catch (Exception $e) {
            $log->logError(ContactoController::class, 'Error al obtener el contacto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }

    /**
     * Crear un nuevo contacto
     */
    public function addContacto(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'cliente_id' => 'required|integer',
                'nombre' => 'required|string|max:255',
                'parentesco_id' => 'nullable|integer',
                'cargo_id' => 'nullable|integer',
                'telefono_1' => 'nullable|string',
                'telefono_2' => 'nullable|string',
                'telefono_3' => 'nullable|string'
            ]);

            $contacto = DB::transaction(function () use ($request) {
                $contacto = Contacto::create($request->all());
                return $contacto->load(['cliente', 'parentesco', 'cargo']);
            });

            $log->logInfo(ContactoController::class, 'Se creó el contacto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $contacto), 201);
        } catch (Exception $e) {
            $log->logError(ContactoController::class, 'Error al crear el contacto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un contacto existente
     */
    public function editContacto(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'parentesco_id' => 'nullable|integer',
                'cargo_id' => 'nullable|integer'
            ]);

            $contacto = DB::transaction(function () use ($request, $id) {
                $contacto = Contacto::findOrFail($id);
                $contacto->update($request->all());
                return $contacto->load(['cliente', 'parentesco', 'cargo']);
            });

            $log->logInfo(ContactoController::class, 'Se actualizó el contacto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $contacto));
        } catch (Exception $e) {
            $log->logError(ContactoController::class, 'Error al actualizar el contacto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar (cambiar estado) de un contacto
     */
    public function deleteContacto($id)
    {
        $log = new Funciones();
        try {
            $contacto = DB::transaction(function () use ($id) {
                $contacto = Contacto::findOrFail($id);
                $contacto->delete();

                return $contacto;
            });

            $log->logInfo(ContactoController::class, 'Se eliminó el contacto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $contacto));
        } catch (Exception $e) {
            $log->logError(ContactoController::class, 'Error al eliminar el contacto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}
