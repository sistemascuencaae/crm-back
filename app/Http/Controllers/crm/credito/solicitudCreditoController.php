<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\credito\solicitudCredito;
use Exception;
use Illuminate\Http\Request;

class solicitudCreditoController extends Controller
{
    public function addSolicitudCredito(Request $request)
    {
        try {
            $solicitudCredito = solicitudCredito::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la solicitud de crédito con éxito', $solicitudCredito));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listSolicitudCreditoByEntidadId($ent_id)
    {
        $solicitudesCredito = solicitudCredito::orderBy("id", "asc")->where('ent_id', $ent_id)->get();

        return response()->json([
            "solicitudesCredito" => $solicitudesCredito,
        ]);
    }

    public function listSolicitudCreditoByRucCedula($ruc_cedula)
    {
        $solicitudesCredito = solicitudCredito::orderBy("id", "asc")->where('ruc_cedula', $ruc_cedula)->get();

        return response()->json([
            "solicitudesCredito" => $solicitudesCredito,
        ]);
    }

}