<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\Partes;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function listado()
    {
        $data = Partes::orderBy('parte_id')->get();

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }

    public function byParte($parte)
    {
        $data = Partes::where('parte_id', $parte)->first();
        if($data){
            return response()->json(RespuestaApi::returnResultado('success', 'Parte Encontrada', $data));
        }else{
            return response()->json(RespuestaApi::returnResultado('error', 'La parte no existe', []));
        }
    }

    public function grabaParte(Request $request)
    {
        try {
            date_default_timezone_set("America/Guayaquil");
            
            $parte_id = 0;
            $fecha_crea = null;
            $fecha_modifica = null;

            if ($request->input('parte_id') == null) {
                $parte_id = Partes::max('parte_id') + 1;
                $fecha_crea = date("Y-m-d h:i:s");
            } else {
                $parte_id = $request->input('parte_id');
                $fecha_crea = $request->input('fecha_crea');
                $fecha_modifica = date("Y-m-d h:i:s");
            }

            $descripcion = $request->input('descripcion');
            $usuario_crea = $request->input('usuario_crea');
            $usuario_modifica = $request->input('usuario_modifica');

            DB::table('gex.partes')->updateOrInsert(
                ['parte_id' => $parte_id],
                [
                'parte_id' => $parte_id,
                'descripcion' => $descripcion,
                'usuario_crea' => $usuario_crea,
                'fecha_crea' => $fecha_crea,
                'usuario_modifica' => $usuario_modifica,
                'fecha_modifica' => $fecha_modifica,
                ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Parte grabada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminaParte($parte) {
        try {
            Partes::where('parte_id',$parte)->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Parte eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}