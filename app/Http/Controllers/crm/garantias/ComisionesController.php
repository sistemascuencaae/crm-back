<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\Comisiones;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComisionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = Comisiones::orderBy('comi_id')->get();

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byComision($comi)
    {
        $data = Comisiones::where('comi_id', $comi)->first();
        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Comision Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La comision no existe', []));
        }
    }

    public function grabaComi(Request $request)
    {
        try {
            DB::transaction(function() use ($request){
                date_default_timezone_set("America/Guayaquil");
            
                $comi_id = 0;
                $fecha_crea = null;
                $fecha_modifica = null;

                if ($request->input('comi_id') == null) {
                    $comi_id = Comisiones::max('comi_id') + 1;
                    $fecha_crea = date("Y-m-d h:i:s");
                } else {
                    $comi_id = $request->input('comi_id');
                    $fecha_crea = $request->input('fecha_crea');
                    $fecha_modifica = date("Y-m-d h:i:s");
                }

                $cumpli_prod_ini = $request->input('cumpli_prod_ini');
                $cumpli_prod_fin = $request->input('cumpli_prod_fin');
                $cumpli_gex_ini = $request->input('cumpli_gex_ini');
                $cumpli_gex_fin = $request->input('cumpli_gex_fin');
                $porc_vendedor = $request->input('porc_vendedor');
                $porc_jfa = $request->input('porc_jfa');
                $porc_jfz = $request->input('porc_jfz');
                $porc_jfv = $request->input('porc_jfv');
                $porc_jfg = $request->input('porc_jfg');
                $usuario_crea = $request->input('usuario_crea');
                $usuario_modifica = $request->input('usuario_modifica');

                DB::table('gex.comisiones')->updateOrInsert(
                    ['comi_id' => $comi_id],
                    [
                    'comi_id' => $comi_id,
                    'cumpli_prod_ini' => $cumpli_prod_ini,
                    'cumpli_prod_fin' => $cumpli_prod_fin,
                    'cumpli_gex_ini' => $cumpli_gex_ini,
                    'cumpli_gex_fin' => $cumpli_gex_fin,
                    'porc_vendedor' => $porc_vendedor,
                    'porc_jfa' => $porc_jfa,
                    'porc_jfz' => $porc_jfz,
                    'porc_jfv' => $porc_jfv,
                    'porc_jfg' => $porc_jfg,
                    'usuario_crea' => $usuario_crea,
                    'fecha_crea' => $fecha_crea,
                    'usuario_modifica' => $usuario_modifica,
                    'fecha_modifica' => $fecha_modifica,
                    ]);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Comision grabada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaComision($comi) {
        try {
            Comisiones::where('comi_id',$comi)->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Comision eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}