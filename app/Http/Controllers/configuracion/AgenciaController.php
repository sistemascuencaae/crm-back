<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\Agencia;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgenciaController extends Controller
{
    public function __construct()
    {
    }

    public function listAllAgencias()
    {
        try {
            $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")->orderBy('nombre', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addAgencia(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                Agencia::create($request->all());

                $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")->orderBy('nombre', 'asc')->get();

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editAgencia(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $agencia = Agencia::findOrFail($id);

                $agencia->update($request->all());

                $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")->orderBy('nombre', 'asc')->get();

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteAgencia(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {

                $agencia = Agencia::findOrFail($id);

                $agencia->delete();

                $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")->orderBy('nombre', 'asc')->get();

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}