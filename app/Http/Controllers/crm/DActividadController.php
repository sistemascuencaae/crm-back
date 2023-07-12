<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\DTipoActividad;
use Exception;
use Illuminate\Http\Request;

class DActividadController extends Controller
{
    public function addDTipoActividad(Request $request)
    {
        try {
            DTipoActividad::create($request->all());

            // $actividades = DTipoActividad::orderBy('id', 'DESC')->get();

            $data = DTipoActividad::with('cTipoActividad')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la Actividad con éxito', $data));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listActividadesByIdCasoId($caso_id)
    {
        try {
            $actividades = DTipoActividad::where('caso_id', $caso_id)->with('cTipoActividad')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo las actividades de este caso con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}