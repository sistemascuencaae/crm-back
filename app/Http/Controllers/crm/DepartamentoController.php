<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Departamento;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartamentoController extends Controller
{

    public function listAllUser()
    {
        $log = new Funciones();
        try {
            $departamentos = Departamento::with('users')->where('estado', true)->get();

            $log->logInfo(DepartamentoController::class, 'Se listo con exito todos los departamentos con usuarios');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $departamentos));
        } catch (\Throwable $e) {
            $log->logError(DepartamentoController::class, 'Error al listar todos los departamentos con usuarios', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function allDepartamento()
    {
        $log = new Funciones();
        try {
            $departamentos = Departamento::orderBy("id", "desc")->where('estado', true)->get();

            // return response()->json([
            //     "departamentos" => $departamentos,
            // ]);

            $log->logInfo(DepartamentoController::class, 'Se listo con exito todos los departamentos activos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $departamentos));
        } catch (Exception $e) {
            $log->logError(DepartamentoController::class, 'Error al listar todos los departamentos activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listDepartamento()
    {
        $log = new Funciones();
        try {
            $departamentos = Departamento::orderBy("id", "desc")->get();

            $log->logInfo(DepartamentoController::class, 'Se listo con exito todos los departamentos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $departamentos));
        } catch (Exception $e) {
            $log->logError(DepartamentoController::class, 'Error al listar todos los departamentos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addDepartamento(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {
                Departamento::create($request->all());

                return Departamento::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            });

            $log->logInfo(DepartamentoController::class, 'Se guardo con exito el departamento');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DepartamentoController::class, 'Error al guardar el departamento', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editDepartamento(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $departamento = Departamento::findOrFail($id);

                $departamento->update($request->all());

                return Departamento::where('id', $id)->first();
            });

            $log->logInfo(DepartamentoController::class, 'Se actualizo con exito el departamento con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DepartamentoController::class, 'Error al actualizar el departamento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteDepartamento($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id) {
                $departamento = Departamento::findOrFail($id);

                $departamento->delete();

                return $departamento;
            });

            $log->logInfo(DepartamentoController::class, 'Se elimino con exito el departamento con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DepartamentoController::class, 'Error al eliminar el departamento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}