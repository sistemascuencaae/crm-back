<?php

namespace App\Http\Controllers\hclinico;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Paciente;
use Illuminate\Http\Request;

class PacienteDosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'byIdentificacion', 'edit'
        ]]);
    }

    public function byIdentificacion($identificacion, $pacId)
    {
        try {
            if($identificacion === '0'){
                $results = Paciente::find($pacId);
            }else{

                $results = Paciente::where('pac_identificacion', $identificacion)->first();
            }
            if ($results) {
                return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $results));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'EL paciente no existe.', null));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th->getMessage()));
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $dataPaciente = $request->all();
            $paciente = Paciente::find($id);
            if ($paciente) {
                $paciente->update($dataPaciente);
                return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $paciente));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Error al editar.', $id));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
    public function add(Request $request)
    {
        try {
            $dataPaciente = $request->all();
            $paciente = Paciente::create($dataPaciente);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $paciente));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
}
