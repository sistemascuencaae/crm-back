<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Cliente;
use App\Models\openceo\Direccion;
use App\Models\openceo\Entidad;
use App\Models\openceo\Telefono;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

use function PHPUnit\Framework\throwException;

class DdocumentoController extends Controller
{
    public function validarFacturaRenegociacion(Request $request)
    {
        //try {

            $doctran = $request->input('doctran');
            $numeroCuotas = $request->input('numeroCuotas');
            $data = DB::selectOne(
                "SELECT count(ddo_doctran) as numero_cuotas
                from public.ddocumento where ddo_doctran = ? and ddo_num_pago <> 999",
                [$doctran]
            );
            if ($data) {
                // if ($numeroCuotas <= $data->numero_cuotas) {
                //     return response()->json(RespuestaApi::returnResultado('error', 'Error en numero de cuota', []));
                // }

                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'La factura no existe.', []));
            }
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        // }
    }

    public function renegociar(Request $request){
        $doctran = $request->input('doctran');
    }
}
