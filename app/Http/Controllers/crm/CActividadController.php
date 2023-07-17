<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CTipoActividad;
use App\Models\crm\DTipoActividad;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;

class CActividadController extends Controller
{
    public function addCTipoActividad(Request $request)
    {
        try {
            CTipoActividad::create($request->all());

            $actividades = CTipoActividad::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();


            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el tipo registro con éxito', $actividades));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoActividadByIdTablero($tab_id)
    {
        try {
            $actividades = CTipoActividad::where('tab_id', $tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo los registros con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoActividadByIdTableroEstadoActivo($tab_id)
    {
        try {
            $actividades = CTipoActividad::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo los registros con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Si vale este listar todos los tableros, solo esta de descomentar aqui, en el api y front
    // public function allCTipoActividades()
    // {
    //     try {
    //         $actividades = CTipoActividad::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo las actividades con éxito', $actividades));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }



    // public function addCActividad(Request $request)
    // {
    //     try {
    //         $cAct = $request->all();
    //         $data = DB::transaction(function () use ($cAct) {
    //             $cActividad = CTipoActividad::create($cAct);
    //             for ($i = 0; $i < sizeof($cAct['actividades']); $i++) {
    //                 $d = DTipoActividad::create([
    //                     "cta_id" => $cActividad['id'],
    //                     "nombre" => $cAct['actividades'][$i]['nombre'],
    //                     // "usuario" => $cAct['actividades'][$i]['usuario'],
    //                     "descripcion" => $cAct['actividades'][$i]['descripcion'],
    //                     "fecha_inicio" => $cAct['actividades'][$i]['fecha_inicio'],
    //                     "fecha_fin" => $cAct['actividades'][$i]['fecha_fin'],
    //                     "fecha_termino" => $cAct['actividades'][$i]['fecha_termino'],
    //                     "requerido" => $cAct['actividades'][$i]['requerido'],
    //                     "estado" => $cAct['actividades'][$i]['estado']
    //                 ]);
    //             }

    //             // return CTipoTarea::with('dTipoTarea')->orderBy("id", "desc")->where('id', $cTarea->id)->get();
    //             return CTipoActividad::with('dTipoActividad')->orderBy("id", "desc")->get();
    //         });

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la Actividad con éxito', $data));

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function editCTipoActividad(Request $request, $id)
    {
        try {
            $actividad = CTipoActividad::findOrFail($id);

            $actividad->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó el registro con éxito', $actividad));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteCTipoActividad($id)
    {
        try {
            $actividad = CTipoActividad::findOrFail($id);

            $actividad->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito el registro', $actividad));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}