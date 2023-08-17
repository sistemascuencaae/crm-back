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


    public function solicitudByEntId($entIdentificacion,$userId)
    {
        try {

            $user = DB::selectOne('SELECT * FROM public.users WHERE id = ?', [$userId]);

            $alm = DB::select('SELECT alm.alm_nombre, us.name as usu_nombre FROM public.users us
            inner join public.puntoventa pve on pve.pve_id = us.pve_id
            inner join public.almacen alm on alm.alm_id = pve.alm_id where us.id = ? limit 1', [$userId]);

            $almNombre = '';
            $userName = '';

            if(sizeof($alm) == 1){
                $almNombre = $alm[0]->alm_nombre;
                $userName = $alm[0]->usu_nombre;
            }
            $solicitudesCredito = AvSolicitudCredito::with('referencias')->where('ent_identificacion', $entIdentificacion)->get();

            $data = (object) [
                "solicitudesCredito" => $solicitudesCredito,
                "almNombre" => $almNombre,
                "userName" => $userName
            ];



            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
