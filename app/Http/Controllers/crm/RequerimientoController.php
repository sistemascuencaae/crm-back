<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Requerimientos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequerimientoController extends Controller
{
    private $log;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->log = new Funciones();
    }

    public function listRequerimientosByFaseId($fase_id)
    {
        try {
            $requerimientos = Requerimientos::where('fase_id', $fase_id)->orderBy('orden', 'ASC')->get();

            $this->log->logInfo(RequerimientoController::class, 'Se listo con exito los requerimientos de la fase con el ID: ' . $fase_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimientos));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al listar los requerimientos de la fase con el ID: ' . $fase_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addRequerimientos(Request $request)
    {
        try {
            $existeReqEquifax = DB::selectOne("SELECT 'equifax' AS campo_a_exist,
            EXISTS (SELECT 1 FROM crm.requerimientos_predefinidos WHERE fase_id = " . $request->input('fase_id') . " and tipo = 'equifax') AS existe_a,
            'equifax enrolamiento cliente' AS campo_b_exist,
            EXISTS (SELECT 1 FROM crm.requerimientos_predefinidos WHERE fase_id = " . $request->input('fase_id') . " and tipo = 'equifax enrolamiento cliente')
            AS existe_b;");
            //echo ('$existeReqEquifax: ' . json_encode($existeReqEquifax));
            //echo ('$request->all(): '.json_encode($request->all()));
            if ($existeReqEquifax) {
                if ($existeReqEquifax->existe_a == true && $request->input('tipo') == 'equifax') {
                    $this->log->logError(RequerimientoController::class, 'Ya existe el requerimiento tipo: ' . $request->input('tipo'));

                    return response()->json(RespuestaApi::returnResultado('error', 'Ya existe el requerimiento tipo: ' . $request->input('tipo'), ''));
                }
                if ($existeReqEquifax->existe_b == true && $request->input('tipo') == 'equifax enrolamiento cliente') {
                    $this->log->logError(RequerimientoController::class, 'Ya existe el requerimiento tipo: ' . $request->input('tipo'));

                    return response()->json(RespuestaApi::returnResultado('error', 'Ya existe el requerimiento tipo: ' . $request->input('tipo'), ''));
                }
            }

            // Verifica si ya existe un requerimiento con el tipo "perfil de cliente" en la misma fase
            $existingRequerimiento = Requerimientos::where('fase_id', $request->input('fase_id'))
                ->where('tipo', 'perfil de cliente')
                ->first();

            if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
                $this->log->logError(RequerimientoController::class, 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre);

                // Si ya existe un requerimiento con tipo "perfil de cliente" en esta fase
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
            } else {
                // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
                $requerimiento = Requerimientos::create($request->all());

                $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->orderBy('orden', 'ASC')->get();

                $this->log->logInfo(RequerimientoController::class, 'Se guardo con exito los requerimientos en la fase con el ID: ' . $request->input('fase_id'));

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
            }

        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al guardar los requerimientos en la fase con el ID: ' . $request->input('fase_id'), $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addRequerimientos(Request $request)
    // {
    //     try {
    //         // Verifica si ya existe un requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('tipo', 'perfil de cliente')
    //             ->first();

    //         // Verifica si ya existe un requerimiento con el mismo valor en el campo "orden"
    //         $existingOrden = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('orden', $request->input('orden'))
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             // Si ya existe un requerimiento con tipo "perfil de cliente" en esta fase
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } elseif ($existingOrden) {
    //             // Si ya existe un requerimiento con el mismo valor en el campo "orden"
    //             return response()->json(RespuestaApi::returnResultado('error', 'El campo "orden" ya existe en otro registro.', ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento = Requerimientos::create($request->all());

    //             $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->orderBy('orden', 'ASC')->get();

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
    //         }

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function editRequerimientos(Request $request, $id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            // Verifica si ya existe otro requerimiento con el tipo "perfil de cliente" en la misma fase
            $existingRequerimiento = Requerimientos::where('fase_id', $requerimiento->fase_id)
                ->where('tipo', 'perfil de cliente')
                ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
                ->first();

            if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
                $this->log->logError(RequerimientoController::class, 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre);

                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
            } else {

                $requerimiento->update($request->all());

                $this->log->logInfo(RequerimientoController::class, 'Se actualizo con exito el requerimiento con el ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $requerimiento));
            }
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al actualizar el requerimiento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function editRequerimientos(Request $request, $id)
    // {
    //     try {
    //         $requerimiento = Requerimientos::findOrFail($id);

    //         // Verifica si ya existe otro requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('tipo', 'perfil de cliente')
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         // Verifica si ya existe un requerimiento con el mismo valor en el campo "orden"
    //         $existingOrden = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('orden', $request->input('orden'))
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } elseif ($existingOrden) {
    //             // Si ya existe un requerimiento con el mismo valor en el campo "orden"
    //             return response()->json(RespuestaApi::returnResultado('error', 'El campo "orden" ya existe en otro registro.', ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento->update($request->all());

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $requerimiento));
    //         }
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


    public function deleteRequerimientos($id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            $requerimiento->delete();

            $this->log->logInfo(RequerimientoController::class, 'Se elimino con exito el requerimiento con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $requerimiento));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al eliminar el requerimiento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
