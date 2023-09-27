<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ClienteCrm;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteCrmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listClienteCrmByEntId(Request $request, $ent_id)
    {
        try {
            $respuesta = ClienteCrm::where('ent_id', $ent_id)->with('referencias.telefonos')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Este metodo solo lo ocupaba cuando queria guardar manualmente el cliente
    public function addClienteCrm(Request $request)
    {
        try {
            $ent_id = $request->input('ent_id');

            $respuesta = DB::transaction(function () use ($request, $ent_id) {

                // Buscar el cliente por ent_id, o crear uno nuevo si no existe.
                $cliente = ClienteCrm::firstOrNew(['ent_id' => $ent_id]);

                // Si es un cliente nuevo, llenar los campos con los valores de la solicitud.
                if (!$cliente->exists) {
                    $cliente->fill($request->all());
                    $cliente->save();
                }

                return $cliente;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


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

    public function editClienteCrm(Request $request, $ent_id)
    {
        try {
            $cliente = ClienteCrm::where('ent_id', $ent_id)->firstOrFail();

            $cliente->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $cliente));
        } catch (Exception $e) {
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