<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\RespuestasCaso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class RespuestasCasoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listRespuestasCasoByTablero($id)
    {
        try {
            // $respuestas = RespuestasCaso::where('tab_id', $id)->with('tipo_estado')->get();
            $respuestas = RespuestasCaso::where('tab_id', $id)->with('fase')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listRespuestasCasoActivoByTablero($id)
    {
        try {
            // $respuestas = RespuestasCaso::where('tab_id', $id)->with('tipo_estado')->get();
            $respuestas = RespuestasCaso::where('tab_id', $id)->where('estado', true)->with('fase')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addRespuestasCaso(Request $request)
    // {
    //     try {
    //         $respuestas = RespuestasCaso::create($request->all());

    //         // $resultado = Estados::where('tab_id', $respuestas->tab_id)->with('tipo_estado')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
    //         $resultado = RespuestasCaso::where('tab_id', $respuestas->tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function addRespuestasCaso(Request $request)
    {
        try {
            // Validar si ya existe un registro con el mismo est_id_actual y resp_id
            $existingRecord = RespuestasCaso::where('fase_id', $request->fase_id)
                ->where('nombre', $request->nombre)
                ->with('fase')
                ->first();

            if ($existingRecord) {
                // Si ya existe un registro con los mismos valores, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE una respuesta con el nombre: ' . $existingRecord->nombre . ', en la fase: ' . $existingRecord->fase->nombre, ''));

            } else {

                $respuestas = RespuestasCaso::create($request->all());

                $resultado = RespuestasCaso::where('tab_id', $respuestas->tab_id)->with('fase')
                    ->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editRespuestasCaso(Request $request, $id)
    {
        try {
            $respuestas = RespuestasCaso::findOrFail($id);

            // Validar si la actualización resultaría en valores duplicados
            $existingRecord = RespuestasCaso::where('fase_id', $request->fase_id)
                ->where('nombre', $request->nombre)
                ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                ->first();

            if ($existingRecord) {
                // Si la actualización resultaría en valores duplicados, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE una respuesta con el nombre: ' . $existingRecord->nombre . ', en la fase: ' . $existingRecord->fase->nombre, ''));

            } else {

                $respuestas->update($request->all());

                $resultado = RespuestasCaso::where('id', $respuestas->id)->with('fase')->first();

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteRespuestasCaso(Request $request, $id)
    {
        try {
            $respuestas = RespuestasCaso::findOrFail($id);

            $respuestas->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}