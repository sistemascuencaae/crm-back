<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ClienteCrm;
use App\Models\crm\TelefonosReferencias;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelefonosReferenciasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addTelefonosReferencias(Request $request)
    {
        try {
            $respuesta = DB::transaction(function () use ($request) {

                $ref = TelefonosReferencias::create($request->all());

                return ClienteCrm::where('id', $ref->id)->first();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTelefonosReferencias(Request $request, $id)
    {
        try {
            $ref = TelefonosReferencias::findOrFail($id);

            $ref->update($request->all());

            $respuesta = TelefonosReferencias::where('id', $ref->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

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


    public function deleteTelefonosReferencias(Request $request, $id)
    {
        try {
            $respuesta = TelefonosReferencias::findOrFail($id);

            $respuesta->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}