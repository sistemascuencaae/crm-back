<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Etiqueta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EtiquetaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addEtiqueta(Request $request)
    {
        try {
            $etiqueta = Etiqueta::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la etiqueta con éxito', $etiqueta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listEtiquetaByCasoId($caso_id)
    {
        $etiquetas = Etiqueta::orderBy("id", "asc")->where('caso_id', $caso_id)->get();

        return response()->json([
            "etiquetas" => $etiquetas,
        ]);
    }

    // public function updateEtiqueta(Request $request, $id)
    // {
    //     try {
    //         $etiqueta = Etiqueta::findOrFail($id);

    //         // $etiqueta->update([
    //         //     "nombre" => $request->nombre,
    //         //     "color" => $request->color,
    //         // ]);

    //         return response()->json(["etiquetas" => $etiqueta,]);
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function deleteEtiqueta($id)
    {
        try {
            $etiqueta = Etiqueta::findOrFail($id);

            $etiqueta->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito la etiqueta', $etiqueta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}