<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Departamento;
use Exception;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{

    public function listAllUser()
    {
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

    public function listDepartamento()
    {
        try {
            $departamentos = Departamento::orderBy("id", "desc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $departamentos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }



    public function addDepartamento(Request $request)
    {
        try {
            Departamento::create($request->all());

            $resultado = Departamento::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editDepartamento(Request $request, $id)
    {
        try {
            $departamento = Departamento::findOrFail($id);

            $departamento->update($request->all());

            $resultado = Departamento::where('id', $departamento->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteDepartamento(Request $request, $id)
    {
        try {
            $departamento = Departamento::findOrFail($id);

            $departamento->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $departamento));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}