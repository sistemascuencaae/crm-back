<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\Formulario;
use App\Models\Formulario\FormUserCompletoView;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list', 'listByDepar', 'formUser', 'byId','listAll'
        ]]);
    }

    public function formUser($formId, $userId)
    {

        try {
            $data = FormUserCompletoView::where('form_id', $formId)->where('user_id', $userId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
    public function listByDepar($id, $userId)
    {
        try {
            $data = Formulario::with(['campo.tipo', 'campo.valor' => function ($query) use ($userId) {
                $query->where('crm.form_valor.user_id', $userId)->get(); // Limitar a un solo resultado
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
            $data = Formulario::with('campo.tipo', 'campo.likert')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function byId($id)
    {
        try {
            $data = Formulario::with('campo.tipo')->where('dep_id', $id)->get();
            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'El id no existe', $id));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
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
