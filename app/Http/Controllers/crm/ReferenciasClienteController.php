<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ClienteCrm;
use App\Models\crm\ReferenciasCliente;
use App\Models\crm\TelefonosReferencias;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenciasClienteController extends Controller
{

    private $log;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->log = new Funciones();
    }

    // metodo que ocupe para insertar los datos de referencias y telefonos de icreativa
    // public function addReferenciasCliente(Request $request)
    // {
    //     try {
    //         $data = $request->input('data'); // Asume que los datos están en una clave 'data' en la solicitud

    //         $respuesta = DB::transaction(function () use ($data) {

    //             foreach ($data as $item) {
    //                 // Buscar el cli_id en otra_tabla basado en el ent_id
    //                 $cliId = ClienteCrm::where('ent_id', $item['ent_id'])->value('id');

    //                 // Crear una nueva instancia de ReferenciaCliente
    //                 $referenciaCliente = new ReferenciasCliente();
    //                 $referenciaCliente->ent_id = $item['ent_id'];
    //                 $referenciaCliente->cli_id = $cliId; // Asignar el cli_id encontrado
    //                 $referenciaCliente->nombre_comercial = $item['nombre_comercial'];
    //                 $referenciaCliente->parentesco = $item['parentesco'];
    //                 $referenciaCliente->email = $item['email'];
    //                 $referenciaCliente->direccion = $item['direccion'];
    //                 $referenciaCliente->save();

    //                 // Iterar sobre los teléfonos y crear instancias de TelefonoReferencia asociadas
    //                 foreach ($item['telefonos'] as $telefonoData) {

    //                     // Verificar si numero_telefono no es null
    //                     if ($telefonoData['numero_telefono'] !== null) {
    //                         $telefonoReferencia = new TelefonosReferencias();
    //                         $telefonoReferencia->ref_id = $referenciaCliente->id; // Asociar el teléfono con la referencia
    //                         $telefonoReferencia->numero_telefono = $telefonoData['numero_telefono'];
    //                         $telefonoReferencia->save();
    //                     }
    //                 }
    //             }
    //         });

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $respuesta));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
    //     }
    // }

    public function addReferenciasCliente(Request $request)
    {
        try {
            DB::beginTransaction();

            // Crea una nueva referencia
            $ref = new ReferenciasCliente($request->except('telefonos'));
            $ref->save();

            // Itera sobre los teléfonos y crea cada uno asociado a la nueva referencia
            foreach ($request->telefonos as $telefonoData) {
                $telefono = new TelefonosReferencias($telefonoData);
                $ref->telefonos()->save($telefono);
            }

            DB::commit();

            $respuesta = ReferenciasCliente::where('id', $ref->id)->with('telefonos')->first();

            $this->log->logInfo(ReferenciasClienteController::class, 'Se guardo con exito las referencias del cliente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $respuesta));
        } catch (Exception $e) {
            DB::rollback();

            $this->log->logError(ReferenciasClienteController::class, 'Error al guardar las referencias del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editReferenciasCliente(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ref = ReferenciasCliente::find($id);
            $ref->update($request->all());

            // Obtén los IDs de los teléfonos existentes en la base de datos
            $telefonosDBIds = $ref->telefonos->pluck('id')->toArray();

            // Obtén los IDs de los teléfonos enviados desde el frontend
            $telefonosFrontendIds = collect($request->telefonos)->pluck('id')->toArray();

            // Encuentra los teléfonos a eliminar
            $telefonosAEliminar = array_diff($telefonosDBIds, $telefonosFrontendIds);
            TelefonosReferencias::whereIn('id', $telefonosAEliminar)->delete();

            foreach ($request->telefonos as $telefonoData) {
                if (isset($telefonoData['id'])) {
                    // Actualiza los teléfonos existentes
                    $telefono = TelefonosReferencias::find($telefonoData['id']);
                    $telefono->update(['numero_telefono' => $telefonoData['numero_telefono'], 'tipo_telefono' => $telefonoData['tipo_telefono']]);
                } else {
                    // Agrega nuevos teléfonos
                    $ref->telefonos()->create(['numero_telefono' => $telefonoData['numero_telefono'], 'tipo_telefono' => $telefonoData['tipo_telefono']]);
                }
            }

            DB::commit();

            $respuesta = ReferenciasCliente::where('id', $ref->id)->with('telefonos')->first();

            $this->log->logInfo(ReferenciasClienteController::class, 'Se actualizo con exito las referencias del cliente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $respuesta));
        } catch (Exception $e) {
            DB::rollback();

            $this->log->logError(ReferenciasClienteController::class, 'Error al actualizar las referencias del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editReferenciaObservacion(Request $request, $referencia_id)
    {
        $log = new Funciones();
        try {
            $referencia = ReferenciasCliente::find($referencia_id);

            DB::transaction(function () use ($referencia, $request) {
                $referencia->update([
                    "observacion" => $request->observacion
                ]);
            });

            $log->logInfo(CasoController::class, 'Se actualizo con exito la observación de la referencia con el ID: ' . $referencia_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $referencia));
        } catch (Exception $e) {
            $log->logError(CasoController::class, 'Error al actualizar la observación de la referencia con el ID: ' . $referencia_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editReferenciaValida(Request $request, $referencia_id)
    {
        $log = new Funciones();
        try {
            $referencia = ReferenciasCliente::find($referencia_id);

            DB::transaction(function () use ($referencia, $request) {
                $referencia->update([
                    "valido" => $request->valido
                ]);
            });

            $log->logInfo(CasoController::class, 'Se actualizo con exito la observación de la referencia con el ID: ' . $referencia_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $referencia));
        } catch (Exception $e) {
            $log->logError(CasoController::class, 'Error al actualizar la observación de la referencia con el ID: ' . $referencia_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // public function editReferenciasCliente(Request $request, $id)
    // {
    //     try {
    //         $ref = ReferenciasCliente::findOrFail($id);

    //         $ref->update($request->all());

    //         $respuesta = ReferenciasCliente::where('id', $ref->id)->with('telefonos')->first();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $respuesta));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // este editar es por ent_id

    // public function editClienteCrm(Request $request, $ent_id)
    // {
    //     try {
    //         $cliente = ClienteCrm::where('ent_id', $ent_id)->firstOrFail();

    //         $cliente->update($request->all());

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $cliente));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function deleteReferenciasCliente(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $respuesta = ReferenciasCliente::find($id);

            // Elimina los teléfonos asociados a la referencia antes de eliminar la referencia
            $respuesta->telefonos()->delete();

            // Elimina la referencia
            $respuesta->delete();

            DB::commit();

            $this->log->logInfo(ReferenciasClienteController::class, 'Se elimino con exito la referencia con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $respuesta));
        } catch (Exception $e) {
            DB::rollback();

            $this->log->logError(ReferenciasClienteController::class, 'Error al eliminar la referencia con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // LIST PARA LAS ACTIVIDADES
    public function listReferenciasByClienteId($cli_id)
    {
        try {
            $respuesta = ReferenciasCliente::where('cli_id', $cli_id)->orderBy('id', 'ASC')->get();

            $this->log->logInfo(ReferenciasClienteController::class, 'Se listo con exito las referencias del cliente con el ID: ' . $cli_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
        } catch (Exception $e) {
            $this->log->logError(ReferenciasClienteController::class, 'Error al listar las referencias del cliente con el ID: ' . $cli_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}