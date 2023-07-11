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
    // public function listActividadesByIdTablero($tab_id)
    // {
    //     try {
    //         $actividades = CTipoActividad::where('tab_id', $tab_id)->with('DTipoActividad')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo las actividades del tablero con éxito', $actividades));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function allCTipoActividades()
    {
        try {
            $actividades = CTipoActividad::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo las actividades con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addCTipoActividad(Request $request)
    {
        try {
            $tipoActividad = CTipoActividad::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el tipo de Actividad con éxito', $tipoActividad));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

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
}