<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\credito\AvSolicitudCredito;
use App\Models\crm\credito\ReferenciasAnexoOpenceo;
use App\Models\crm\credito\solicitudCredito;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class solicitudCreditoController extends Controller
{
    public function addSolicitudCredito(Request $request)
    {
        try {
            $solicitudCredito = solicitudCredito::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $solicitudCredito));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editSolicitudCredito(Request $request, $id)
    {
        try {
            $solicitudCredito = solicitudCredito::findOrFail($id);

            $solicitudCredito->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $solicitudCredito));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listSolicitudCreditoByEntidadId($ent_id)
    {
        try {
            $solicitudesCredito = solicitudCredito::orderBy("id", "asc")->where('ent_id', $ent_id)->get();

            // return response()->json([
            //     "solicitudesCredito" => $solicitudesCredito,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $solicitudesCredito));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listSolicitudCreditoByRucCedula($ruc_cedula)
    {
        try {
            $solicitudesCredito = solicitudCredito::orderBy("id", "asc")->where('ruc_cedula', $ruc_cedula)->get();

            // return response()->json([
            //     "solicitudesCredito" => $solicitudesCredito,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $solicitudesCredito));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function solicitudByIdentificacion($entIdentificacion, $userId)
    {
        try {
            $user = DB::selectOne('SELECT u.name, alm.alm_nombre  from public.users u
            inner join public.puntoventa pve on pve.pve_id = u.pve_id
            inner join public.almacen alm on alm.alm_id = pve.alm_id
            where u.id = ?', [$userId]);

            $solicitudesCredito = AvSolicitudCredito::with('referencias')->where('ent_identificacion', $entIdentificacion)->get();

            $data = (object) [
                "solicitudesCredito" => $solicitudesCredito,
                "almNombre" => $user->alm_nombre,
                "userName" => $user->name
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}