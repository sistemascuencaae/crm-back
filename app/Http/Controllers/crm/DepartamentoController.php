<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Departamento;
use Exception;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{

    public function listAllUser(){
        try {
            $departamentos = Departamento::with('')->where('estado', true)->get();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    public function allDepartamento()
    {
        try {
            $departamentos = Departamento::orderBy("id", "desc")->where('estado', true)->get();

            // return response()->json([
            //     "departamentos" => $departamentos,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $departamentos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
