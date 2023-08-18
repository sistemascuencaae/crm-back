<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CFormulario;
use Illuminate\Http\Request;

class CFormularioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAll(){
        try {
            $formularios = CFormulario::with('dformulario')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito.', $formularios));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar formularios.', $th->getMessage()));
        }

    }
    public function listActivos(Request $request){

    }

    public function add(Request $request){
        try {
            $newFormulario = $request->all();
            $dForms = $request->input('dforms');
            $data = '';




            return response()->json(RespuestaApi::returnResultado('success', 'Formulario creado con exito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear formulario.', $th->getMessage()));
        }
    }

    public function getFormById($formId){
        try {
            $data = CFormulario::with('dformulario')->where('id', $formId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Formulario obtenido con exito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener formulario.', $th->getMessage()));
        }
    }

    public function edit(){

    }

    public function delete(){

    }


}
