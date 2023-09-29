<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Caso;
use App\Models\crm\ClienteCrm;
use App\Models\crm\credito\AvSolicitudCredito;
use App\Models\crm\credito\ReferenciasAnexoOpenceo;
use App\Models\crm\credito\SolicitudCredito;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class solicitudCreditoController extends Controller
{
    public function addSolicitudCredito(Request $request)
    {
        try {

            $casoId = $request->input('caso_id');
            $solicitudCredito = $this->obtenerSolicitudCreditoActualizada($casoId);









            // // START Bloque de código que genera un registro de auditoría manualmente
            // $audit = new Audits();
            // $audit->user_id = Auth::id();
            // $audit->event = 'created';
            // $audit->auditable_type = solicitudCredito::class;
            // $audit->auditable_id = $solicitudCredito->id;
            // $audit->user_type = User::class;
            // $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            // $audit->url = $request->fullUrl();
            // // Establecer old_values y new_values
            // $audit->old_values = json_encode($solicitudCredito);
            // $audit->new_values = json_encode([]);
            // $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            // $audit->accion = 'addSolicitudCredito';
            // $audit->save();
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


    // public function solicitudByIdentificacion($entIdentificacion, $userId)
    // {
    //     try {
    //         $user = DB::selectOne('SELECT u.name, alm.alm_nombre  from public.users u
    //         inner join public.puntoventa pve on pve.pve_id = u.pve_id
    //         inner join public.almacen alm on alm.alm_id = pve.alm_id
    //         where u.id = ?', [$userId]);

    //         $solicitudesCredito = AvSolicitudCredito::with('referencias')->where('ent_identificacion', $entIdentificacion)->get();

    //         $data = (object) [
    //             "solicitudesCredito" => $solicitudesCredito,
    //             "almNombre" => $user->alm_nombre,
    //             "userName" => $user->name
    //         ];

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function solicitudByIdentificacion($entIdentificacion, $userId)
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

    public function obtenerSolicitudCreditoActualizada($casoId){
        try {
            $solicitudCredito = SolicitudCredito::with('cliente.telefonos', 'cliente.referencias.telefonos')->where('caso_id',$casoId)->first();

            if($solicitudCredito){
                return $solicitudCredito;
            }


            $caso = Caso::with('userCreador')->find($casoId);
            $almacen = DB::selectOne('SELECT alm.* FROM public.users usu
            inner join public.puntoventa pve on pve.pve_id = usu.pve_id
            inner join public.almacen alm on alm.alm_id = pve.alm_id where usu.id = ? limit 1',[$caso->user_id]);
            if($caso){
                $cliente = ClienteCrm::with('referencias.telefonos')->find($caso->cliente_id);
                $nuevaSC = new SolicitudCredito();
                $nuevaSC->cliente_id = $cliente->id;
                $nuevaSC->fecha_actual = Carbon::now()->format('Y-m-d H:i:s');
                $nuevaSC->vendedor = $caso->userCreador->usu_alias;
                $nuevaSC->agencia = $almacen->alm_nombre;
                $nuevaSC->codigo_cliente = $cliente->identificacion;
                $nuevaSC->nacionalidad = $cliente->nacionalidad;
                $nuevaSC->ruc_cedula = $cliente->identificacion;
                $nuevaSC->nombre_razon_social = $cliente->nombres.' '.$cliente->apellidos;
                $nuevaSC->nivel_educacion = $cliente->nivel_educacion;
                $nuevaSC->cargas_familiares = $cliente->numero_dependientes;
                //$nuevaSC->telefono_domicilio = "";
                //$nuevaSC->numero_celular = "";
                $nuevaSC->calle_principal = $cliente->calle_principal;
                $nuevaSC->calle_secundaria = $cliente->calle_secundaria;
                $nuevaSC->referencia_direccion = $cliente->referencias_direccion;
                $nuevaSC->provincia = $cliente->prv_nombre;
                $nuevaSC->canton = $cliente->ctn_nombre;
                $nuevaSC->parroquia = $cliente->prq_nombre;
                //$nuevaSC->actividad_economica = "";
                $nuevaSC->nombre_empresa = $cliente->nombre_empresa;
                $nuevaSC->tipo_empresa = $cliente->tipo_empresa;
                $nuevaSC->direccion = $cliente->direccion;
                //$nuevaSC->telefono_trabajo1 = "";
                //$nuevaSC->telefono_trabajo2 = "";
                $nuevaSC->fecha_ingreso = $cliente->fecha_ingreso;
                $nuevaSC->total_ingresos = $cliente->ingresos_totales;
                $nuevaSC->total_egresos = $cliente->gastos_totales;
                $nuevaSC->total_ingresos_egresos = ($cliente->ingresos_totales - $cliente->gastos_totales);
                $nuevaSC->referencias = json_encode($cliente->referencias);
                $nuevaSC->caso_id = $casoId;
                $nuevaSC->save();
                $solicitudCredito = SolicitudCredito::with('cliente.telefonos', 'cliente.referencias.telefonos')->find($nuevaSC->id);
                return $solicitudCredito;

            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

}

