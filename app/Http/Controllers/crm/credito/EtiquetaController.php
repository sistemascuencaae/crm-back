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

    public function store(Request $request)
    {
        try {
            $etiqueta = Etiqueta::create($request->all());

            // $data = DB::select('select * from crm.etiquetas');

            // return response()->json(["archivo" => $etiqueta,]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la etiqueta con éxito', $etiqueta));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function index($tar_id)
    {
        $etiquetas = Etiqueta::orderBy("id", "asc")->where('tar_id',$tar_id)->get();

        return response()->json([
            "etiquetas" => $etiquetas,
        ]);
    }

    // public function edit(Request $request, $id)
    // {
    //     try {
    //         $etiqueta = Etiqueta::findOrFail($id);

    //         // $etiqueta->update([
    //         //     "nombre" => $request->nombre,
    //         // ]);

    //         $etiqueta->update($request->all());

    //         return response()->json(["etiquetas" => $etiqueta,]);
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function destroy($id)
    {
        try {
            $archivo = Etiqueta::findOrFail($id);

            $archivo->delete();

            // return response()->json(["message" => 200]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito lla etiqueta', $archivo));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}