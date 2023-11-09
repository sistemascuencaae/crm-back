<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ClienteCrm;
use App\Models\crm\TelefonosCliente;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteCrmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listClienteCrmById(Request $request, $id)
    {
        try {
            $respuesta = ClienteCrm::where('id', $id)->with('telefonos', 'referencias.telefonos')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Este metodo solo lo ocupaba cuando queria guardar manualmente el cliente
    // public function addClienteCrm(Request $request)
    // {
    //     try {
    //         $ent_id = $request->input('ent_id');

    //         $respuesta = DB::transaction(function () use ($request, $ent_id) {

    //             // Buscar el cliente por ent_id, o crear uno nuevo si no existe.
    //             $cliente = ClienteCrm::firstOrNew(['ent_id' => $ent_id]);

    //             // Si es un cliente nuevo, llenar los campos con los valores de la solicitud.
    //             if (!$cliente->exists) {
    //                 $cliente->fill($request->all());
    //                 $cliente->save();
    //             }

    //             return $cliente;
    //         });

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $respuesta));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


    // public function editClienteCrm(Request $request, $ent_id)
    // {
    //     try {
    //         $cliente = ClienteCrm::findOrFail($ent_id);

    //         $cliente->update($request->all());

    //         $respuesta = ClienteCrm::where('ent_id', $cliente->ent_id)->first();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $respuesta));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

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


    public function editClienteCrm(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $cliente = ClienteCrm::where('ent_id', $id)->firstOrFail();
            $cliente->update($request->except('telefonos'));

            // Obtén los IDs de los teléfonos existentes en la base de datos
            $telefonosDBIds = $cliente->telefonos->pluck('id')->toArray();

            // Obtén los IDs de los teléfonos enviados desde el frontend
            $telefonosFrontendIds = collect($request->telefonos)->pluck('id')->toArray();

            // Encuentra los teléfonos a eliminar
            $telefonosAEliminar = array_diff($telefonosDBIds, $telefonosFrontendIds);
            TelefonosCliente::whereIn('id', $telefonosAEliminar)->delete();

            foreach ($request->telefonos as $telefonoData) {
                if (isset($telefonoData['id'])) {
                    // Actualiza los teléfonos existentes
                    $telefono = TelefonosCliente::findOrFail($telefonoData['id']);
                    $telefono->update(['numero_telefono' => $telefonoData['numero_telefono'], 'tipo_telefono' => $telefonoData['tipo_telefono']]);
                } else {
                    // Agrega nuevos teléfonos sin asignar un 'id'
                    $nuevoTelefono = new TelefonosCliente(['numero_telefono' => $telefonoData['numero_telefono'], 'tipo_telefono' => $telefonoData['tipo_telefono']]);
                    $cliente->telefonos()->save($nuevoTelefono);
                }
            }

            DB::commit();

            $respuesta = ClienteCrm::where('id', $cliente->id)->with('telefonos')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $respuesta));
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }







    // public function deleteClienteCrm(Request $request, $id)
    // {
    //     try {
    //         $respuesta = ClienteCrm::findOrFail($id);

    //         $respuesta->delete();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuesta));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

}