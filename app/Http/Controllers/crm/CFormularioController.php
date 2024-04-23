<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CFormulario;
use Illuminate\Http\Request;

class CFormularioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAll()
    {
        $log = new Funciones();
        try {
            $formularios = CFormulario::with('dformulario')->get();

            $log->logInfo(CFormularioController::class, 'Se listo con exito los formularios');

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito.', $formularios));
        } catch (\Throwable $e) {
            $log->logError(CFormularioController::class, 'Error al listar los formularios', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar formularios.', $e->getMessage()));
        }

    }
    public function listActivos(Request $request)
    {

    }

    public function add(Request $request)
    {
        $log = new Funciones();
        try {
            $newFormulario = $request->all();
            $dForms = $request->input('dforms');
            $data = '';

            $log->logInfo(CFormularioController::class, 'Se guardo con exito el formulario');

            return response()->json(RespuestaApi::returnResultado('success', 'Formulario creado con exito.', $data));
        } catch (\Throwable $e) {
            $log->logError(CFormularioController::class, 'Error al guardar el formulario', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear formulario.', $e->getMessage()));
        }
    }

    public function getFormById($formId)
    {
        $log = new Funciones();
        try {
            $data = CFormulario::with('dformulario')->where('id', $formId)->get();

            $log->logInfo(CFormularioController::class, 'Se listo con exito el formulario, con el ID: ' . $formId);

            return response()->json(RespuestaApi::returnResultado('success', 'Formulario obtenido con exito.', $data));
        } catch (\Throwable $e) {
            $log->logError(CFormularioController::class, 'Error al listar el formulario, con el ID: ' . $formId, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener formulario.', $e->getMessage()));
        }
    }

    public function edit()
    {

    }

    public function delete()
    {

    }


}
