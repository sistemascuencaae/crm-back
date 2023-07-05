<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Nota;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addNota(Request $request)
    {
        try {
            $nota = Nota::create($request->all());

            $data = DB::select('select * from crm.nota where caso_id ='.$request->caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la nota con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listNotaByCasoId($caso_id)
    {
        $notas = Nota::orderBy("id", "desc")->where('caso_id', $caso_id)->get();

        return response()->json([
            "notas" => $notas
        ]);
    }

    public function updateNota(Request $request, $id)
    {
        try {
            $nota = Nota::findOrFail($id);

            // $nota->update([
            //     "nombre" => $request->nombre,
            // ]);

            $nota->update($request->all());

            return response()->json(["notas" => $nota]);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteNota($id)
    {
        try {
            $nota = Nota::findOrFail($id);

            $nota->delete();

            // return response()->json(["message" => 200]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito la nota', $nota));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
