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

    public function obtenerSolicitudCreditoActualizada($casoId)
    {
        try {
            $solicitudCredito = SolicitudCredito::with('cliente.telefonos', 'cliente.referencias.telefonos')->where('caso_id', $casoId)->first();
            // if($solicitudCredito){
            //     return $solicitudCredito;
            // }
            $caso = Caso::with('userCreador')->find($casoId);
            $almacen = DB::selectOne('SELECT alm.* FROM public.users usu
            inner join public.puntoventa pve on pve.pve_id = usu.pve_id
            inner join public.almacen alm on alm.alm_id = pve.alm_id where usu.id = ? limit 1', [$caso->user_id]);
            if ($caso) {
                $cliente = ClienteCrm::with('referencias.telefonos')->find($caso->cliente_id);
                if (!$solicitudCredito) {
                    $solicitudCredito = new SolicitudCredito();
                    $solicitudCredito->fecha_actual = Carbon::now()->format('Y-m-d H:i:s');
                }
                $solicitudCredito->cliente_id = $cliente->id;
                $solicitudCredito->vendedor = $caso->userCreador->usu_alias;
                $solicitudCredito->agencia = $almacen->alm_nombre;
                $solicitudCredito->codigo_cliente = $cliente->identificacion;
                $solicitudCredito->nacionalidad = $cliente->nacionalidad;
                $solicitudCredito->ruc_cedula = $cliente->identificacion;
                $solicitudCredito->nombre_razon_social = $cliente->nombres . ' ' . $cliente->apellidos;
                $solicitudCredito->nivel_educacion = $cliente->nivel_educacion;
                $solicitudCredito->cargas_familiares = $cliente->numero_dependientes;
                //$solicitudCredito->telefono_domicilio = "";
                //$solicitudCredito->numero_celular = "";
                $solicitudCredito->calle_principal = $cliente->calle_principal;
                $solicitudCredito->calle_secundaria = $cliente->calle_secundaria;
                $solicitudCredito->referencia_direccion = $cliente->referencias_direccion;
                $solicitudCredito->provincia = $cliente->prv_nombre;
                $solicitudCredito->canton = $cliente->ctn_nombre;
                $solicitudCredito->parroquia = $cliente->prq_nombre;
                //$solicitudCredito->actividad_economica = "";
                $solicitudCredito->nombre_empresa = $cliente->nombre_empresa;
                $solicitudCredito->tipo_empresa = $cliente->tipo_empresa;
                $solicitudCredito->direccion = $cliente->direccion;
                //$solicitudCredito->telefono_trabajo1 = "";
                //$solicitudCredito->telefono_trabajo2 = "";
                $solicitudCredito->fecha_ingreso = $cliente->fecha_ingreso;
                $solicitudCredito->total_ingresos = $cliente->ingresos_totales;
                $solicitudCredito->total_egresos = $cliente->gastos_totales;
                $solicitudCredito->total_ingresos_egresos = ($cliente->ingresos_totales - $cliente->gastos_totales);
                //$solicitudCredito->referencias = json_encode($cliente->referencias);
                $solicitudCredito->caso_id = $casoId;
                $solicitudCredito->save();
                if (!$solicitudCredito) {
                    $solicitudCredito = SolicitudCredito::with('cliente.telefonos', 'cliente.referencias.telefonos')->find($solicitudCredito->id);
                }
                return $solicitudCredito;
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
