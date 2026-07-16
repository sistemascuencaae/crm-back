<?php

namespace App\Http\Controllers\garancheck;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\ValidacionCedulaRucService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GarancheckController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ! Busqueda del cliente para el motor congnitivo
    public function consultar_cliente_motor_cognitivo(Request $request)
    {
        try {
            $tipoidentificacion = trim($request->input('tipo_identificacion'));
            $identificacion = trim($request->input('identificacion'));

            if (empty($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
            }
            if (!in_array($tipoidentificacion, ['1', '2', '3'])) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar un tipo de identificación válido (1: cédula, 2: RUC, 3: pasaporte).', null));
            }

            $identificacionBusqueda = $identificacion;

            if ($tipoidentificacion == 1) {
                if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
                }
            } elseif ($tipoidentificacion == 2) {
                if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
                }
            } else {
                // Pasaporte: sin algoritmo de verificación, solo formato genérico
                if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El pasaporte ingresado no es válido', null));
                }
            }

            $data = DB::selectOne('SELECT * FROM public.af_cliente_motor_cognitivo(?)', [$identificacionBusqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
