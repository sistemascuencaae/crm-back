<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CActividadCliente;
use App\Models\crm\DActividadCliente;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CActividadClienteController extends Controller
{
    public function listCActividadClienteByIdTablero($tab_id)
    {
        $log = new Funciones();
        try {
            $actividades = CActividadCliente::where('tab_id', $tab_id)->with('dActividadCliente')->orderBy('id', 'DESC')->get();

            $log->logInfo(CActividadClienteController::class, 'Se listo con exito las actividades del cliente por tab_id: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(CActividadClienteController::class, 'Error al listar las actividades del cliente por tab_id: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    public function listCACReqCaso()
    {
        $log = new Funciones();
        try {
            $actividades = CActividadCliente::with('dActividadCliente')->get();

            $log->logInfo(CActividadClienteController::class, 'Se listo con exito las actividades del cliente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(CActividadClienteController::class, 'Error al listar las actividades del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    public function addCActividadCliente(Request $request)
    {
        $log = new Funciones();
        try {
            $cAct = $request->all();
            $data = DB::transaction(function () use ($cAct) {
                $cActividad = CActividadCliente::create($cAct);
                for ($i = 0; $i < sizeof($cAct['actividades']); $i++) {
                    DActividadCliente::create([
                        "cac_id" => $cActividad['id'],
                        "nombre" => $cAct['actividades'][$i]['nombre'],
                        "cuota" => $cAct['actividades'][$i]['cuota'],
                        "entrada" => $cAct['actividades'][$i]['entrada'],
                        "plazo" => $cAct['actividades'][$i]['plazo'],
                        "tab_id" => $cAct['actividades'][$i]['tab_id'],
                        "descripcion" => $cAct['actividades'][$i]['descripcion'],
                    ]);
                }

                return CActividadCliente::where('tab_id', $cActividad['tab_id'])->with('dActividadCliente')->orderBy('id', 'DESC')->get();
            });

            $log->logInfo(CActividadClienteController::class, 'Se guardo con exito la actividad del cliente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(CActividadClienteController::class, 'Error al guardar la actividad del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editCActividadCliente(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $eliminados = $request->input('eliminados');
            $actividades = $request->input('actividades');
            $cactividad = $request->all();

            $tab = DB::transaction(function () use ($request, $cactividad, $id, $eliminados, $actividades) {
                CActividadCliente::where('id', $id)
                    ->update([
                        'nombre' => $cactividad['nombre'],
                    ]);

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.dactividad_cliente WHERE cac_id = " . $id . " and id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($actividades); $i++) {
                    $tabl = DActividadCliente::where('cac_id', $id)->where('id', $actividades[$i])->first();

                    if (!$tabl) {
                        DActividadCliente::create([
                            "cac_id" => $id,
                            "nombre" => $actividades[$i]['nombre'],
                            "cuota" => $actividades[$i]['cuota'],
                            "entrada" => $actividades[$i]['entrada'],
                            "plazo" => $actividades[$i]['plazo'],
                            "tab_id" => $actividades[$i]['tab_id'],
                            "descripcion" => $actividades[$i]['descripcion'],
                        ]);
                    } else {
                        $tabl->update([
                            "nombre" => $actividades[$i]['nombre'],
                            "cuota" => $actividades[$i]['cuota'],
                            "entrada" => $actividades[$i]['entrada'],
                            "plazo" => $actividades[$i]['plazo'],
                            "tab_id" => $actividades[$i]['tab_id'],
                            "descripcion" => $actividades[$i]['descripcion'],
                        ]);
                    }

                }

                return $cactividad;
            });

            $dataRe = CActividadCliente::with('dActividadCliente')->where('id', $id)->first();

            $log->logInfo(CActividadClienteController::class, 'Se actualizo con exito la actividad del cliente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $dataRe));
        } catch (Exception $e) {
            $log->logError(CActividadClienteController::class, 'Error al actualizar la actividad del cliente', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
