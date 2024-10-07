<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\AutoTrataDatos;
use App\Models\Formulario\FormSeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormAuthDatosCliController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list', 'add',
            'listAlmacenes',
            'getAlmacenId'
        ]]);
    }

    public function store()
    {
        try {
            $arrayUno = DB::select("SELECT * FROM almacen");
            $data = (object)[
                "usuarios" => $arrayUno
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }


    public function add(Request $request)
    {
        try {
            $dataInput = $request->all();
            $nombreCompleto = $dataInput['apellidos'] . ' ' . $dataInput['nombres'];
            $dataInput['nombre_completo'] = $nombreCompleto;
            $data = AutoTrataDatos::create($dataInput);
            return response()->json(RespuestaApi::returnResultado('success', 'Guardado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar.', $th));
        }
    }

    public function getAlmacenId($id)
    {
        try {
            $data = DB::selectOne("SELECT * FROM public.almacen where alm_id = ?",[$id]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function listAlmacenes()
    {
        try {
            $data = DB::select("SELECT * FROM public.almacen where alm_activo = true order by 1 asc");
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }




}
