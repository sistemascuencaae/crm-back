<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\Formulario;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list', 'listByDepar'
        ]]);
    }

    public function listByDepar($id)
    {
        try {
            $data = Formulario::with('campo.tipo','campo.valor')->where('dep_id', $id)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function list()
    {
        try {
            $data = Formulario::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function listAll()
    {
        try {
            $data = Formulario::withTrashed()->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
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
