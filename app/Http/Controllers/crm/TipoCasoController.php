<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoCaso;
use App\Models\Formulario\Formulario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoCasoController extends Controller
{
    // public function addTipoCaso(Request $request)
    // {
    //     try {
    //         $tipoCaso = TipoCaso::create($request->all());

    //         $resultado = TipoCaso::where('tab_id', $tipoCaso->tab_id)->with('cTipoTarea.dTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function addTipoCaso(Request $request)
    {
        try {
            // Validar si ya existe un registro con el mismo ctt_id
            $data = DB::transaction(function () use ($request) {
                $cttId = $request->input('ctt_id');
                $existingTipoCaso = TipoCaso::where('ctt_id', $cttId)->first();
                if ($existingTipoCaso) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La Tarea ya esta asignada o un Tipo Caso', ''));
                }
                // Si no existe, crea un nuevo registro
                $tipoCaso = TipoCaso::create($request->all());
                $resultado = TipoCaso::where('tab_id', $tipoCaso->tab_id)->with('cTipoTarea.dTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
                $form_id = $request->input('form_id');
                if ($form_id) {
                    $formtipocasoId = DB::table('crm.formulario_tipo_caso')->insert([
                        'form_id' => $form_id,
                        'tc_id' => $tipoCaso->id,
                        'tab_id' => $request->input('tab_id'),
                    ]);
                }
                return $resultado;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function listTipoCasoByIdTablero($tab_id)
    {
        try {
            $resultado = TipoCaso::where('tab_id', $tab_id)->with('cTipoTarea.dTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listByIdTipoCasoActivo($tc_id)
    {
        try {
            $resultado = TipoCaso::where('id', $tc_id)->with('cTipoTarea.dTipoTarea')->where('estado', true)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTipoCasoByIdTableroEstadoActivo($tab_id)
    {
        try {
            $resultado = TipoCaso::where('tab_id', $tab_id)->with('cTipoTarea.dTipoTarea')->where('estado', true)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function editTipoCaso(Request $request, $id)
    // {
    //     try {
    //         $tipoCaso = TipoCaso::findOrFail($id);

    //         $tipoCaso->update($request->all());

    //         $resultado = TipoCaso::where('id', $tipoCaso->id)
    //                 ->with('cTipoTarea.dTipoTarea')
    //                 ->first();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function editTipoCaso(Request $request, $id)
    {
        try {
            $tipoCaso = TipoCaso::findOrFail($id);

            // Validar si ya existe un registro con el mismo ctt_id
            $cttId = $request->input('ctt_id');
            $existingTipoCaso = TipoCaso::where('ctt_id', $cttId)
                ->where('id', '<>', $id) // Excluir el registro actual de la búsqueda
                ->first();

            if ($existingTipoCaso) {
                return response()->json(RespuestaApi::returnResultado('error', 'La Tarea ya esta asignada o un Tipo Caso', ''));
            }

            $tipoCaso->update($request->all());

            $resultado = TipoCaso::where('id', $tipoCaso->id)
                ->with('cTipoTarea.dTipoTarea')
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function deleteTipoCaso($id)
    {
        try {
            $resultado = TipoCaso::findOrFail($id);

            $resultado->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }



    public function addFormularioTc($tab_id)
    {
        $log = new Funciones();

        try {
            $formularios = DB::select("SELECT * FROM crm.formulario fo
            inner join crm.formulario_tipo_caso ftc on ftc.form_id = fo.id
            where ftc.tab_id = $tab_id");

            $log->logInfo(CTareaController::class, 'Se listo con exito los formularios del tablero, con el ID: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $formularios));
        } catch (Exception $e) {
            $log->logError(
                CTareaController::class,
                'Error al listar las tareas del tablero, con el ID: ' . $tab_id,
                $e
            );

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
