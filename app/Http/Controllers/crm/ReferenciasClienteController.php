<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ClienteCrm;
use App\Models\crm\ReferenciasCliente;
use App\Models\crm\TelefonosReferencias;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenciasClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
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
            $data = $request->input('data'); // Asume que los datos están en una clave 'data' en la solicitud

            $respuesta = DB::transaction(function () use ($data) {

                foreach ($data as $item) {
                    // Buscar el cli_id en otra_tabla basado en el ent_id
                    $cliId = ClienteCrm::where('ent_id', $item['ent_id'])->value('id');

                    // Crear una nueva instancia de ReferenciaCliente
                    $referenciaCliente = new ReferenciasCliente();
                    $referenciaCliente->ent_id = $item['ent_id'];
                    $referenciaCliente->cli_id = $cliId; // Asignar el cli_id encontrado
                    $referenciaCliente->nombre_comercial = $item['nombre_comercial'];
                    $referenciaCliente->parentesco = $item['parentesco'];
                    $referenciaCliente->email = $item['email'];
                    $referenciaCliente->direccion = $item['direccion'];
                    $referenciaCliente->save();

                    // Iterar sobre los teléfonos y crear instancias de TelefonoReferencia asociadas
                    foreach ($item['telefonos'] as $telefonoData) {

                        // Verificar si numero_telefono no es null
                        if ($telefonoData['numero_telefono'] !== null) {
                            $telefonoReferencia = new TelefonosReferencias();
                            $telefonoReferencia->ref_id = $referenciaCliente->id; // Asociar el teléfono con la referencia
                            $telefonoReferencia->numero_telefono = $telefonoData['numero_telefono'];
                            $telefonoReferencia->save();
                        }
                    }
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function editReferenciasCliente(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ref = ReferenciasCliente::findOrFail($id);
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
                    $telefono = TelefonosReferencias::findOrFail($telefonoData['id']);
                    $telefono->update(['numero_telefono' => $telefonoData['numero_telefono']]);
                } else {
                    // Agrega nuevos teléfonos
                    $ref->telefonos()->create(['numero_telefono' => $telefonoData['numero_telefono']]);
                }
            }

            DB::commit();

            $respuesta = ReferenciasCliente::where('id', $ref->id)->with('telefonos')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $respuesta));
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
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
            $respuesta = ReferenciasCliente::findOrFail($id);

            $respuesta->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}