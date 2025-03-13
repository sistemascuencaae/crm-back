<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Models\crm\TipoCaso;
use App\Models\Formulario\FormSeccion;
use App\Models\Formulario\Formulario;
use App\Models\Formulario\FormularioTipoCaso;
use App\Models\Formulario\Parametro;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;

class TipoCasoController extends Controller
{

    public function addTipoCaso(Request $request)
    {
        try {
            $error = null;
            $exitoso = null;

            // Validar si ya existe un registro con el mismo ctt_id
            DB::transaction(function () use ($request, &$error, &$exitoso) {
                $ctipo_tarea_id = $request->input('ctipo_tarea_id');
                // Obtener y transformar el array 'tab' en una cadena con el delimitador 'ൠ'
                // $tabs = $request->input('tab');  // Array de tab
                // $tabString = implode('ൠ', array_map(function ($item) {
                //     return $item['nombre']; // Extraer el nombre de cada tab
                // }, $tabs));

                // Añadir la cadena transformada al request
                $request->merge(['tab' => json_encode($request->tab)]);

                // $request->merge(['tab' => json_encode($request->tab)]); // Convierte el array en una cadena JSON

                // Si no existe, crea un nuevo registro
                $tipoCaso = TipoCaso::create($request->all());

                // Obtener el resultado después de la creación
                // $exitoso = TipoCaso::where('tab_id', $tipoCaso->tab_id)->with('tipoCasoCTipoTarea.dTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();


                $exitoso = TipoCaso::where('tab_id', $tipoCaso->tab_id)
                ->with('tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
                ->orderBy('estado', 'DESC')
                ->orderBy('id', 'DESC')
                ->get();


                // // Convertir 'tab' en array para cada resultado
                // $exitoso->each(function ($tipoCaso) {
                //     if (!empty($tipoCaso->tab)) {
                //         // Convertir la cadena de 'tab' a un array utilizando el delimitador 'ൠ'
                //         $tipoCaso->tab = explode('ൠ', $tipoCaso->tab);
                //     }
                // });

                // Inserción en la tabla 'tipo_caso_ctipo_tarea'
                if ($ctipo_tarea_id) {
                    DB::table('crm.tipo_caso_ctipo_tarea')->insert([
                        'tipo_caso_id' => $tipoCaso->id,
                        'ctipo_tarea_id' => $ctipo_tarea_id,
                    ]);
                }

                return null;
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function listTipoCasoByIdTablero($tab_id)
    {
        try {
            // Obtener los resultados de la base de datos
            // $resultado = TipoCaso::where('tab_id', $tab_id)
            //     ->with(
            //         'tipoCasoCTipoTarea.dTipoTarea',
            //         'formTipoCaso.formulario'
            //     )
            //     ->orderBy('estado', 'DESC')
            //     ->orderBy('id', 'DESC')
            //     ->get();

            $resultado = TipoCaso::where('tab_id', $tab_id)
                ->with('tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
                ->orderBy('estado', 'DESC')
                ->orderBy('id', 'DESC')
                ->get();

            // // Convertir 'tab' a un array después de obtener los resultados
            // $resultado->each(function ($tipoCaso) {
            //     if (!empty($tipoCaso->tab)) {
            //         // Convertir la cadena de 'tab' a un array utilizando el delimitador 'ൠ'
            //         $tipoCaso->tab = explode('ൠ', $tipoCaso->tab);
            //     }
            // });

            // Retornar los resultados con el atributo 'tab' convertido en array
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listByIdTipoCasoActivo($tc_id)
    {
        try {
            // $resultado = TipoCaso::where('id', $tc_id)->with('cTipoTarea.dTipoTarea')->where('estado', true)->first();
            $resultado = TipoCaso::where('id', $tc_id)->where('estado', true)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTipoCasoByIdTableroEstadoActivo($tab_id)
    {
        try {
            // $resultado = TipoCaso::where('tab_id', $tab_id)->with('cTipoTarea.dTipoTarea')->where('estado', true)->orderBy('id', 'DESC')->get();
            $resultado = TipoCaso::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTipoCaso(Request $request, $id)
    {
        try {
            $tipoCaso = TipoCaso::findOrFail($id);

            // // Validar si ya existe un registro con el mismo ctt_id
            // $cttId = $request->input('ctt_id');
            // $existingTipoCaso = TipoCaso::where('ctt_id', $cttId)
            //     ->where('id', '<>', $id) // Excluir el registro actual de la búsqueda
            //     ->first();

            // if ($existingTipoCaso) {
            //     return response()->json(RespuestaApi::returnResultado('error', 'La Tarea ya esta asignada o un Tipo Caso', ''));
            // }

            $request->merge(['tab' => json_encode($request->tab)]);

            $tipoCaso->update($request->all());

            // $form_id = $request->input('form_id');

            // if ($form_id) {

            //     $formtipocaso = FormularioTipoCaso::where('tc_id', $tipoCaso->id)->first();

            //     if ($formtipocaso) {
            //         $formtipocaso->update([
            //             'form_id' => $form_id,
            //         ]);
            //         //echo 'actualizado';
            //         // echo ('$form_id: '.json_encode($form_id));
            //         // echo ('$tipoCaso->id: '.json_encode($tipoCaso->id));

            //         // echo ('$formtipocaso: ' . json_encode($formtipocaso));
            //     } else {
            //         $formtipocasoId = DB::table('crm.formulario_tipo_caso')->insert([
            //             'form_id' => $form_id,
            //             'tc_id' => $tipoCaso->id,
            //             'tab_id' => $request->input('tab_id'),
            //         ]);
            //     }
            // }

            // $resultado = TipoCaso::where('id', $tipoCaso->id)
            // ->with('cTipoTarea.dTipoTarea', 'formTipoCaso.formulario')
            // ->first();
            
            $resultado = TipoCaso::where('id', $tipoCaso->id)
            ->with('tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
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

    public function getByTipoCasIdFormu1($tcId)
    {
        $log = new Funciones();

        try {
            $formulario = DB::selectOne("SELECT * from crm.formulario_tipo_caso where tc_id = $tcId");

            //$log->logInfo(CTareaController::class, 'Se listo con exito los formularios del tablero, con el ID: ' . $tcId);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $formulario));
        } catch (Exception $e) {
            // $log->logError(
            //     CTareaController::class,
            //     'Error al listar las formulario del tablero, con el ID: ' . $tcId,
            //     $e
            // );

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function getByTipoCasIdFormu($tcId)
    {
        //try {
        $parametros = Parametro::with('parametroHijos')->get();
        $form = DB::selectOne("SELECT * from crm.formulario_tipo_caso where tc_id = $tcId");
        $formulario = Formulario::with([
            'campo.tipo',
            'campo.likert',
            'campo.parametro.parametroHijos',
            'campo.valor' => function ($query) use ($tcId) {
                $query->where('pac_id', 0);
            },
        ])->find($form->form_id);
        $secciones = FormSeccion::where('form_id', $form->form_id)
            ->where('estado', true)
            ->orderBy('orden', 'asc')
            ->get();

        //$campoController = new CampoController();

        //$totalesSecciones = $campoController->getTotalesSecciones($formId, $pacId);
        ///$totalGlobalForm = $campoController->getTotalGlobalForm($formId, $pacId);
        //$camposImprimir = $this->camposImprimir($formId, $pacId);
        $data = (object) [
            "secciones" => $secciones,
            "parametros" => $parametros,
            "formulario" => $formulario,
            "totalGlobalForm" => [],
            "totalesSecciones" => [],
            "camposImprimir" => []
        ];
        return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        // } catch (\Throwable $th) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        // }
    }
}
