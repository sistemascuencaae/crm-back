<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\FormCampo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CampoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'full',
            'add',
            'byId',
            'list',
            'listAll',
            'edit',
            'deleted',
            'restoreById',
            'delete',
            'deleteById'
        ]]);
    }
    public function list()
    {
        try {
            $data = FormCampo::all();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function listAll()
    {
        try {
            $data = FormCampo::withTrashed()->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function full($id)
    {
        try {
            $empleado = FormCampo::with('valor')->find($id);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito.', $empleado));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function add(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                $dataCampo = $request->all();
                $newCampo = FormCampo::create($dataCampo);
                return $newCampo;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
    public function edit(Request $request, $id)
    {

        try {
            $data = DB::transaction(function () use ($request, $id) {
                $campoData = $request->all();
                $campo = FormCampo::findOrFail($id);
                $campo->update($campoData);
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
    public function deleteById($id)
    {
        try {
            $data = FormCampo::find($id);
            if ($data) {
                $data->delete();
                return response()->json(RespuestaApi::returnResultado('success', 'Eliminado con éxito.', $data));
            }
            return response()->json(RespuestaApi::returnResultado('error', 'No existe', $id));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function restoreById($id)
    {
        try {
            $data = FormCampo::withTrashed()->find($id);
            $data->restore();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function byId($id)
    {
        try {
            $data = FormCampo::find($id);
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
