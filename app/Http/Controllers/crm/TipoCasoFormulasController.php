<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\EstadosFormulas;
use App\Models\crm\TipoCasoFormulas;
use Exception;
use Illuminate\Http\Request;

class TipoCasoFormulasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listTpoCasoFormulas(Request $request)
    {
        try {
            $data = TipoCasoFormulas::with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTpoCasoFormulasActivos(Request $request)
    {
        try {
            $data = TipoCasoFormulas::with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")->where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addTipoCasoFormulas(Request $request)
    {
        try {
            // Validar si ya existe un registro con el mismo est_id_actual y resp_id
            $existingRecord = TipoCasoFormulas::where('tab_id', $request->tab_id)
                ->where('tc_id', $request->tc_id)
                ->with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")
                ->first();

            if ($existingRecord) {
                // Si ya existe un registro con los mismos valores, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores tablero: ' . $existingRecord->tablero->nombre . ' y Tipo caso: ' . $existingRecord->tipoCaso->nombre, ''));

            } else {

                // Si no existe un registro con los mismos valores, crea el nuevo registro
                TipoCasoFormulas::create($request->all());

                $resultado = TipoCasoFormulas::with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")
                    ->orderBy('id', 'DESC')
                    ->get();

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }







    

    public function editTipoCasoFormulas(Request $request, $id)
    {
        try {
            $respuestas = EstadosFormulas::findOrFail($id);

            // Validar si la actualización resultaría en valores duplicados
            $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                ->where('resp_id', $request->resp_id)
                ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                ->first();

            if ($existingRecord) {
                // Si la actualización resultaría en valores duplicados, devuelve un error
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre, ''));

            } else {

                $respuestas->update($request->all());

                $resultado = EstadosFormulas::where('id', $respuestas->id)
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
                    ->first();

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteTipoCasoFormulas(Request $request, $id)
    {
        try {
            $respuestas = EstadosFormulas::findOrFail($id);

            $respuestas->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}