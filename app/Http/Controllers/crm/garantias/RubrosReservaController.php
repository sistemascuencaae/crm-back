<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\RubrosReservas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RubrosReservaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = DB::select("select rr.rr_id, rr.descripcion, rr.porc_calculo as porcentaje, case when rr.estado = 'A' then 'ACTIVO' else 'DESACTIVO' end as estado
                            from gex.rubro_reserva rr");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byRubro($rubro)
    {
        $data = RubrosReservas::select()->where('rr_id', $rubro)->first();

        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Rubro de Reserva Encontrado', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'El Rubro de Reserva no existe', []));
        }
    }

    public function grabaRubro(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
            
                $rr_id = 0;
                $fecha_crea = null;
                $fecha_modifica = null;

                if ($request->input('rr_id') == null) {
                    $rr_id = RubrosReservas::max('rr_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $rr_id = $request->input('rr_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }

                $descripcion = $request->input('descripcion');
                $porc_calculo = $request->input('porc_calculo');
                $estado = $request->input('estado');
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');

                DB::table('gex.rubro_reserva')->updateOrInsert(
                    ['rr_id' => $rr_id],
                    [
                    'rr_id' => $rr_id,
                    'descripcion' => $descripcion,
                    'porc_calculo' => $porc_calculo,
                    'estado' => $estado,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Rubro de Reserva grabado con exito', []));
            
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaRubro($rubro) {
        try {
            DB::transaction(function() use ($rubro){
                DB::table('gex.rubro_reserva')->where('rr_id',$rubro)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Rubro de Reserva eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}