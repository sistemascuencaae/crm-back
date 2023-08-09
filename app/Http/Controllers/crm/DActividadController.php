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
            $dta = DTipoActividad::create($request->all());

            // $actividades = DTipoActividad::orderBy('id', 'DESC')->get();

            $data = DTipoActividad::with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->where('caso_id', $dta->caso_id)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listActividadesByIdCasoId($caso_id)
    {
        try {
            $actividades = DTipoActividad::where('caso_id', $caso_id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateDActividad(Request $request, $id)
    {
        try {
            $actividad = DTipoActividad::findOrFail($id);

            $actividad->update($request->all());

            $data = DTipoActividad::where('id', $id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listActividadesByUserId($user_id)
    {
        try {
            $actividades = DTipoActividad::where('user_id', $user_id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}