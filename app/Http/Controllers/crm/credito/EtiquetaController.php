<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Etiqueta;
use Exception;
use Illuminate\Http\Request;

class EtiquetaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addEtiqueta(Request $request)
    {
        $log = new Funciones();
        try {
            $etiqueta = Etiqueta::create($request->all());

            $log->logInfo(EtiquetaController::class, 'Se guardo con exito la Etiqueta');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $etiqueta));
        } catch (Exception $e) {
            $log->logError(EtiquetaController::class, 'Error al guardar la Etiqueta', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listEtiquetaByCasoId($caso_id)
    {
        $log = new Funciones();
        try {
            $etiquetas = Etiqueta::orderBy("id", "asc")->where('caso_id', $caso_id)->get();

            $log->logInfo(EtiquetaController::class, 'Se listo con exito las Etiquetas del caso #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $etiquetas));
        } catch (Exception $e) {
            $log->logError(EtiquetaController::class, 'Error al listar las Etiquetas del caso #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
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
        $log = new Funciones();
        try {
            $etiqueta = Etiqueta::findOrFail($id);

            $etiqueta->delete();

            $log->logInfo(EtiquetaController::class, 'Se elimino con exito la Etiqueta, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $etiqueta));
        } catch (Exception $e) {
            $log->logError(EtiquetaController::class, 'Error al eliminar la Etiqueta, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}