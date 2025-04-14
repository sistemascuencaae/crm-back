<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Models\crm\TipoCaso;
use App\Models\configuracion\TipoCasoTablero;
use App\Models\crm\TipoCasoCTipoTarea;
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

                // Añadir la cadena transformada al request
                $request->merge(['tab' => json_encode($request->tab)]);

                // Si no existe, crea un nuevo registro
                $tipoCaso = TipoCaso::create($request->all());

                // Inserción en la tabla 'tipo_caso_ctipo_tarea'
                if ($ctipo_tarea_id) {
                    TipoCasoCTipoTarea::create([
                        'tipo_caso_id' => $tipoCaso->id,
                        'ctipo_tarea_id' => $ctipo_tarea_id,
                    ]);
                }


                // Verificar que el array de tab_id esté presente
                if ($request->has('tab_id') && is_array($request->tab_id) && $tipoCaso->id) {
                    // Iteramos sobre el array de tab_id y creamos las relaciones en la tabla tipo_caso_tablero
                    foreach ($request->tab_id as $tabId) {
                        TipoCasoTablero::create([
                            'tipo_caso_id' => $tipoCaso->id, // Asegúrate de usar el ID de tipoCaso correspondiente
                            'tab_id' => $tabId,
                        ]);
                    }
                }


                $exitoso = TipoCaso::with('form', 'tipo_caso_tablero.tablero', 'tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
                            ->orderBy('id', 'ASC')
                            ->get();

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

    public function listAllTipoCaso()
    {
        try {
            // $resultado = TipoCasoTablero::with('tablero', 'tipo_caso.form', 'tipo_caso.tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
            //     ->orderBy('id', 'ASC')
            //     ->get();


            $resultado = TipoCaso::with('form', 'tipo_caso_tablero.tablero', 'tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
                            ->orderBy('id', 'ASC')
                            ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listTipoCasoByIdTablero($tab_id)
    {
        try {
            // $resultado = TipoCaso::where('tab_id', $tab_id)
            //     ->with('tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
            //     ->orderBy('estado', 'DESC')
            //     ->orderBy('id', 'DESC')
            //     ->get();
    
            $resultado = TipoCasoTablero::where('tab_id', $tab_id)
                            ->with('tipo_caso')
                            ->orderBy('id', 'ASC')
                            ->get();

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
            // $resultado = TipoCaso::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            $resultado = TipoCasoTablero::where('tab_id', $tab_id)
            ->with('tipo_caso')
            ->orderBy('id', 'DESC')
            ->get();


            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTipoCaso(Request $request, $id)
    {
        try {
            $respuesta = DB::transaction(function () use ($request, $id) {
            $tipoCaso = TipoCaso::findOrFail($id);

            // Eliminar la relación si 'ctipo_tarea_id_eliminar' está presente
            if ($request->has('ctipo_tarea_id_eliminar') && $request->ctipo_tarea_id_eliminar != null) {
                // Eliminar la relación de la tabla intermedia si existe
                DB::table('crm.tipo_caso_ctipo_tarea')
                    ->where('tipo_caso_id', $tipoCaso->id)
                    ->where('ctipo_tarea_id', $request->ctipo_tarea_id_eliminar)
                    ->delete();
            }

            // Crear o actualizar la relación 'ctipo_tarea_id' si está presente
            if ($request->has('ctipo_tarea_id') && $request->ctipo_tarea_id != null) {
                // Verificar si ya existe una relación con este tipo de tarea, si no, insertarla
                DB::table('crm.tipo_caso_ctipo_tarea')->updateOrInsert(
                    ['tipo_caso_id' => $tipoCaso->id, 'ctipo_tarea_id' => $request->ctipo_tarea_id] // Aquí puedes agregar otros campos si es necesario
                );
            }


            // Eliminar la relación si 'tab_id_eliminar' está presente
            if ($request->has('tab_id_eliminar') && $request->tab_id_eliminar != null) {
                // Eliminar la relación de la tabla intermedia si existe
                foreach ($request->tab_id_eliminar as $tab_eliminar) {
                    DB::table('crm.tipo_caso_tablero')
                    ->where('tipo_caso_id', $tipoCaso->id)
                    ->where('tab_id', $tab_eliminar)
                    ->delete();
                }
            }

   

            // Verificar que el array de tab_id esté presente
            if ($request->has('tab_id') && is_array($request->tab_id) && $tipoCaso->id) {
                // Iteramos sobre el array de tab_id y creamos las relaciones en la tabla tipo_caso_tablero
                foreach ($request->tab_id as $tabId) {
                    TipoCasoTablero::create([
                        'tipo_caso_id' => $tipoCaso->id, // Asegúrate de usar el ID de tipoCaso correspondiente
                        'tab_id' => $tabId,
                    ]);
                }
            }

            $request->merge(['tab' => json_encode($request->tab)]);

            $tipoCaso->update($request->all());

            return TipoCaso::with('form', 'tipo_caso_tablero.tablero', 'tipoCasoCTipoTarea.cTipoTarea.dTipoTarea')
            ->orderBy('id', 'ASC')
            ->get();
        });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteTipoCaso($id)
    {
        try {
            $respuesta = DB::transaction(function () use ($id) {
                $resultado = TipoCaso::findOrFail($id);

                $resultado->delete();

                return $resultado;
            });
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuesta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
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
