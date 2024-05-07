<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\FormSeccion;
use App\Models\Formulario\Formulario;
use App\Models\Formulario\FormUserCompletoView;
use App\Models\Formulario\Parametro;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list', 'listByDepar', 'formUser', 'listAll', 'storeA', 'storeB', 'formUser', 'getTotalesSecciones', 'impresion', 'listAnonimos', 'storeCasoForm'
        ]]);
    }

    public function storeA($formId)
    {
        try {
            $parametros = Parametro::with('parametroHijos')->get();
            $formulario = Formulario::with([
                'campo.tipo',
                'campo.likert',
                'campo.parametro.parametroHijos',
            ])->find($formId);
            $secciones = FormSeccion::where('form_id', $formId)
                ->orderBy('orden', 'asc')
                ->get();

            $data = (object) [
                "secciones" => $secciones,
                "parametros" => $parametros,
                "formulario" => $formulario
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function addFormulario(Request $request)
    {

        $data = $request->all();

        $tableroId = $request->input('tabId');
        $tipoForm = $request->input('tipoFormulario');




        try {
            $depar = null;
            if ($tableroId) {
                $depar = DB::selectOne("SELECT * from crm.tablero where id = $tableroId");
            }

            $formId = DB::table('crm.formulario')->insertGetId([
                'nombre' => $request->input('nombre'),
                'descripcion' => $request->input('descripcion'),
                'estado' => $request->input('estado'),
                'dep_id' => $depar ? $depar->dep_id : null,
                'tab_id' => $tableroId ? $tableroId : null,
                'tipo' => $tipoForm,
            ]);

            $formulario = DB::selectOne("SELECT * FROM crm.formulario WHERE id = $formId");

            // $idFormTiCam = DB::table('crm.formulario_tipo_caso')->insertGetId([
            //     'form_id' => $id,
            //     'tc_id' =>
            //     'tab_id' =>
            // ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $formulario));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function cargarFormulario($formId) {

        try {

            $parametros = Parametro::with('parametroHijos')->get();
            $formulario = Formulario::with([
                'campo.tipo',
                'campo.likert',
                'campo.parametro.parametroHijos',
                'campo.valor' => function ($query)  {
                    $query->where('key', 0);
                },
            ])->find($formId);
            $secciones = FormSeccion::where('form_id', $formId)
                ->where('estado', true)
                ->orderBy('orden', 'asc')
                ->get();
            $data = (object) [
                "secciones" => $secciones,
                "parametros" => $parametros,
                "formulario" => $formulario,
                //"keyFormulario" => $keyFormulario
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }

    }



    public function storeB($formId, $pacId)
    {
        try {
            $parametros = Parametro::with('parametroHijos')->get();
            $formulario = Formulario::with([
                'campo.tipo',
                'campo.likert',
                'campo.parametro.parametroHijos',
            ])->find($formId);
            $secciones = FormSeccion::where('form_id', $formId)
                ->orderBy('orden', 'asc')
                ->get();

            $campoController = new CampoController();

            $totalesSecciones = $campoController->getTotalesSecciones($formId, $pacId);
            $totalGlobalForm = $campoController->getTotalGlobalForm($formId, $pacId);
            $camposImprimir = $this->camposImprimir($formId, $pacId);
            $data = (object) [
                "secciones" => $secciones,
                "parametros" => $parametros,
                "formulario" => $formulario,
                "totalGlobalForm" => $totalGlobalForm,
                "totalesSecciones" => $totalesSecciones,
                "camposImprimir" => $camposImprimir
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function formUser($formId, $pacId)
    {

        try {
            $data = FormUserCompletoView::where('form_id', $formId)->where('pac_id', $pacId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
    public function listByDepar($id, $pacId)
    {
        try {
            $data = Formulario::with(['campo.tipo', 'campo.valor' => function ($query) use ($pacId) {
                $query->where('crm.form_valor.pac_id', $pacId)->get(); // Limitar a un solo resultado
            }])->where('dep_id', $id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function listAnonimos()
    {
        try {
            $data = DB::select("SELECT * from crm.formularios_anonimos where fecha_resuelto notnull");
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function listAll()
    {
        try {
            $data = Formulario::withTrashed()
                ->with(['campo' => function ($query) {
                    $query->withTrashed()->with('tipo', 'likert', 'parametro.parametroHijos');
                }],)
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $formData = $request->all();
                $form = Formulario::findOrFail($id);
                $form->update($formData);
                return Formulario::with('campo.tipo', 'campo.likert')->find($id);
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }

    public function impresion($formId, $pacId)
    {
        $data = $this->camposImprimir($formId, $pacId);
        return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
    }

    public function camposImprimir($formId, $pacId)
    {
        $data = DB::select("SELECT
            fc.orden,
            fc.form_secc_id,
            ftc.nombre as tipo_nombre,
            fv.user_id,
            fv.pac_id,
            fc.titulo,
            fv.valor_texto,
            fv.valor_entero,
            fv.valor_date,
            fv.valor_decimal,
            fv.valor_boolean,
            fv.valor_json,
            fv.valor_array
            from
            crm.form_campo fc
            left join crm.form_campo_valor fcv on fcv.campo_id = fc.id
            left join crm.form_valor fv on fv.id = fcv.valor_id
            left join crm.form_tipo_campo ftc on ftc.id = fc.tipo_campo_id
            where fc.form_id = ? and fv.pac_id = ? or fv.pac_id isnull order by 1 asc", [$formId, $pacId]);
        return $data;
    }

    public function obtenerFormularioCompleto($id)
    {
        $data = Formulario::withTrashed()
            ->with(['campo' => function ($query) {
                $query->withTrashed()->with('tipo', 'likert', 'parametro.parametroHijos');
            }])
            ->where('id', $id)
            ->first();
        return $data;
    }
    public function listFormByIdTablero($tab_id)
    {
        $log = new Funciones();

        try {
            $formularios = DB::select("SELECT * FROM crm.formulario fo where fo.tab_id = $tab_id");


            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $formularios));
        } catch (Exception $e) {
            //  $log->logError(CTareaController::class, 'Error al listar las tareas del tablero, con el ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function storeCasoForm($casoId)
    {
        try {
            $formCaso = DB::selectOne("SELECT fo.id from crm.caso ca
                inner join crm.form_valor fv on fv.caso_id = ca.id
                inner join crm.form_campo_valor fcv on fcv.valor_id = fv.id
                inner join crm.form_campo fc on fc.id = fcv.campo_id
                left join crm.formulario_seccion fsc on fsc.id = fc.form_secc_id
                left join crm.formulario fo on fo.id = fsc.form_id
                where ca.id = ? limit 1", [$casoId]);
            $parametros = Parametro::with('parametroHijos')->get();
            $formulario = Formulario::with([
                'campo.tipo',
                'campo.likert',
                'campo.parametro.parametroHijos',
                'campo.valor' => function ($query) use ($casoId) {
                    $query->where('caso_id', $casoId);
                },
            ])->find($formCaso->id);
            $secciones = FormSeccion::where('form_id', $formCaso->id)
                ->where('estado', true)
                ->orderBy('orden', 'asc')
                ->get();

            //$campoController = new CampoController();

            //$totalesSecciones = $campoController->getTotalesSecciones($formId, $pacId);
            //$totalGlobalForm = $campoController->getTotalGlobalForm($formId, $pacId);
            //$camposImprimir = $this->camposImprimir($formId, $pacId);
            $data = (object) [
                "secciones" => $secciones,
                "parametros" => $parametros,
                "formulario" => $formulario
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
}
