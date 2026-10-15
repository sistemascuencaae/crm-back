<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\SolicitudesCreditosWeb;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Migrado de ecommerce-back (formulario institucional /formulario)
class SolicitudesCreditosWebController extends Controller
{

    public function addSolicitudCreditoWeb(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                // Obtener la dirección MAC del servidor (no es posible obtener la MAC del cliente desde el lado del servidor)
                // $mac = exec('getmac');
                $mac = "0.0.0.0";

                // Guardar la solicitud de crédito web maquetado o a mano
                $respuesta = SolicitudesCreditosWeb::create([
                    'identificacion' => $request->identificacion,
                    'tipoidentificacion' => $request->tipoidentificacion,
                    'nombres' => $request->nombres,
                    'apellidos' => $request->apellidos,
                    'direccion' => $request->direccion,
                    'prv_nombre' => $request->prv_nombre,
                    'ctn_nombre' => $request->ctn_nombre,
                    'prq_nombre' => null, // null porque no se manda desde el front
                    'email' => $request->email,
                    'telefono' => $request->telefono,
                    'ip' => $request->ip,
                    'mac' => $mac,
                    'aceptar_terminos_condiciones' => $request->aceptar_terminos_condiciones ?? true, // true porque desde la pagina web no me envia este valor y se estaba guardando en null (True porque en la pagina web el cliente si esta aceptando los terminos y condiciones)
                    'confirmacion_terminos_condiciones' => false, // por defecto false, true se hace cuando el usuario o cliente acepte o confirme desde el email
                    'latitud' => $request->latitud,
                    'longitud' => $request->longitud,
                    'formulario_tipo' => $request->formulario_tipo ?? 0,
                    'empresa' => $request->empresa,
                    'informacion_ip' => $request->informacion_ip,
                    'producto_acomprar' => $request->producto_acomprar,
                ]);

                return $respuesta;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function autorizarTratamientoDatos($empresa = null, $id)
    {
        try {
            $data = DB::transaction(function () use ($empresa, $id) {
                $solicitud = SolicitudesCreditosWeb::find($id);

                if ($solicitud) {

                    $solicitud->update([
                        'confirmacion_terminos_condiciones' => true,
                    ]);

                    return $solicitud;

                } else {
                    return 'No existe la solicitud con el ID: ' . $id;
                }

            });

            $tablaEmpresa = DB::table('crm.empresa')->where('nombre', $empresa)->first();

            if ($tablaEmpresa) {

                // si existe Empresa es el nombre, ejemplo 'DIPOR'
                if ($empresa == $tablaEmpresa->nombre) {

                    $objEmpresa = DB::table('crm.empresa')
                        ->where('nombre', $tablaEmpresa->nombre)
                        ->first();

                    $objParametro = DB::table('crm.parametro')
                        ->where('abreviacion', 'NAS')
                        ->first();

                    // Pasar el JSON a la vista
                    return view('mail.confirmacionTerminosCondiciones', ['objEmpresa' => $objEmpresa, 'objParametro' => $objParametro]);

                } else {

                    // este else lo dejo por si queremos ocupar el endPoint por si solo y no desde el Email, me devuelva la data
                    return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));

                }

            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'No existe la empresa: ', $empresa));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
