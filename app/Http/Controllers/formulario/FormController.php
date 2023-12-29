<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\FormSeccion;
use App\Models\Formulario\Formulario;
use App\Models\Formulario\FormUserCompletoView;
use App\Models\Formulario\Parametro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Stmt\TryCatch;

class FormController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list', 'listByDepar', 'formUser', 'listAll', 'storeA', 'storeB', 'formUser', 'getTotalesSecciones', 'impresion'
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
                ->where('estado', true)
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


    public function storeB($formId, $pacId)
    {
        try {
            $parametros = Parametro::with('parametroHijos')->get();
            $formulario = Formulario::with([
                'campo.tipo',
                'campo.likert',
                'campo.parametro.parametroHijos',
                'campo.valor' => function ($query) use ($pacId) {
                    $query->where('pac_id', $pacId);
                },
            ])->find($formId);
            $secciones = FormSeccion::where('form_id', $formId)
                ->where('estado', true)
                ->orderBy('orden', 'asc')
                ->get();

            $campoController = new CampoController();

            $totalesSecciones = $campoController->getTotalesSecciones($formId, $pacId);
            $camposImprimir = $this->camposImprimir($formId, $pacId);
            $data = (object) [
                "secciones" => $secciones,
                "parametros" => $parametros,
                "formulario" => $formulario,
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

    public function listAll()
    {
        try {
            //$data = Formulario::with('campo.tipo', 'campo.campoLikerts.formCampoLikert')->get();
            // $data = Formulario::with('campo.tipo', 'campo.likert')->withTrashed()->get();
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

    // public function byId($id)
    // {
    //     try {
    //         $userId = Auth::id();
    //         $data = Formulario::with([
    //             'campo.tipo',
    //             'campo.likert',
    //             'campo.parametro.parametroHijos',
    //             'campo.valor' => function ($query) use ($userId) {
    //                 $query->where('user_id', $userId);
    //             },
    //             //->orderBy('orden', 'asc') colocar esto para ordenar los campos
    //         ])
    //             ->find($id);
    //         if ($data) {
    //             return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
    //         } else {
    //             return response()->json(RespuestaApi::returnResultado('error', 'El id no existe', $id));
    //         }
    //     } catch (\Throwable $th) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
    //     }
    // }

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
}



    // public function listByDepar($id)
    // {
    //     try {
    //         $data = Formulario::with([
    //             'campo' => function ($query) {
    //                 $query->select('crm.form_campo.id', 'nombre', 'descripcion', 'titulo', 'requerido', 'marcado', 'updated_at', 'form_id');
    //             },
    //             'campo.valor' => function ($query) {
    //                 $query->select('form_valor.id', 'valor_texto', 'tipo', 'campo_id', 'valor_id');
    //             }
    //         ])->select('id', 'nombre', 'descripcion', 'updated_at', 'estado', 'dep_id')
    //             ->where('dep_id', $id)
    //             ->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
    //     } catch (\Throwable $th) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
    //     }
    // }


    //     public function listByDepar($id, $userId)
    // {
    //     try {
    //         $data = Formulario::with(['campo.tipo', 'campo.valor' => function ($query) use ($userId) {
    //             $query->where('crm.form_valor.user_id', $userId)->get(); // Limitar a un solo resultado
    //         }])->where('dep_id', $id)->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
    //     } catch (\Throwable $th) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
    //     }
    // }
