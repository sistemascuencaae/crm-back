<?php

namespace App\Http\Controllers\directorio;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;

use App\Models\directorio\Directorio;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

class DirectorioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' =>
        [
            'listDirectorioCrm',
        ]]);
    }

    public function listDirectorioCrm()
    {
        try {
            $data = Directorio::orderBy('zona', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function addDirectorio(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                Directorio::create($request->all());

                $resp = Directorio::orderBy('zona', 'asc')->get();
                return $resp;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editDirectorio(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $reporte = Directorio::find($id);

                $reporte->update($request->all());

                $resp = Directorio::orderBy('zona', 'asc')->get();

                return $resp;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteDirectorio($id)
    {
        try {
            $data = DB::transaction(function () use ($id) {
                $reporte = Directorio::find($id);

                $reporte->delete();

                $resp = Directorio::orderBy('zona', 'asc')->get();

                return $resp;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}