<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\credito\AvSolicitudCredito;
use App\Models\crm\credito\ReferenciasAnexoOpenceo;
use App\Models\crm\credito\solicitudCredito;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class solicitudCreditoController extends Controller
{
    public function addSolicitudCredito(Request $request)
    {
        try {
            $solicitudCredito = solicitudCredito::create($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = solicitudCredito::class;
            $audit->auditable_id = $solicitudCredito->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($solicitudCredito);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addSolicitudCredito';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $solicitudCredito));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editSolicitudCredito(Request $request, $id)
    {
        try {
            $solicitudCredito = solicitudCredito::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $solicitudCredito;
            $audit->old_values = json_encode($valorAntiguo);


            $solicitudCredito->update($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = solicitudCredito::class;
            $audit->auditable_id = $solicitudCredito->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->new_values = json_encode($solicitudCredito);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'editSolicitudCredito';
            $audit->save();
            // END Auditoria

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

    public function solicitudByIdentificacion2($entIdentificacion, $userId)
    {
        try {
            $user = DB::selectOne('SELECT u.name, alm.alm_nombre  from public.users u
        inner join public.puntoventa pve on pve.pve_id = u.pve_id
        inner join public.almacen alm on alm.alm_id = pve.alm_id
        where u.id = ?', [$userId]);

            $solicitudCredito = AvSolicitudCredito::with('referencias')->where('ent_identificacion', $entIdentificacion)->first();

            $data = (object) [
                "solictudCredito" => $solicitudCredito,
                "almNombre" => $user->alm_nombre,
                "userName" => $user->name
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}