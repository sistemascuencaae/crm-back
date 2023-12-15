<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoCasoFormulas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoCasoFormulasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listTpoCasoFormulasById($tab_id, $tc_id)
    {
        try {
            $data = TipoCasoFormulas::where([
                ['tab_id', $tab_id],
                ['tc_id', $tc_id]
            ])->with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")->first();

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'No hay ninguna fórmula con este tipo de caso, comuníquese con el administrador, por favor.', ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
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

            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, &$error, &$exitoso) {

                // Validar si ya existe un registro con el mismo est_id_actual y resp_id
                $existingRecord = TipoCasoFormulas::where('tab_id', $request->tab_id)
                    ->where('tc_id', $request->tc_id)
                    ->with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")
                    ->first();

                if ($existingRecord) {
                    // Si ya existe un registro con los mismos valores, devuelve un error
                    $error = 'Ya EXISTE un registro con los valores tablero: ' . $existingRecord->tablero->nombre . ' y Tipo caso: ' . $existingRecord->tipoCaso->nombre;
                    return null;

                } else {

                    // Si no existe un registro con los mismos valores, crea el nuevo registro
                    TipoCasoFormulas::create($request->all());

                    $resultado = TipoCasoFormulas::with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")
                        ->orderBy('id', 'DESC')
                        ->get();

                    $exitoso = $resultado;
                    return null;
                }

            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTipoCasoFormulas(Request $request, $id)
    {
        try {

            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, $id, &$error, &$exitoso) {
                $respuestas = TipoCasoFormulas::findOrFail($id);

                // Validar si la actualización resultaría en valores duplicados
                $existingRecord = TipoCasoFormulas::where('tab_id', $request->tab_id)
                    ->where('tc_id', $request->tc_id)
                    ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                    ->first();

                if ($existingRecord) {
                    // Si la actualización resultaría en valores duplicados, devuelve un error
                    $error = 'Ya EXISTE un registro con los valores tablero: ' . $existingRecord->tablero->nombre . ' y Tipo caso: ' . $existingRecord->tipoCaso->nombre;
                    return null;

                } else {

                    $respuestas->update($request->all());

                    $resultado = TipoCasoFormulas::where('id', $respuestas->id)
                        ->with("departamento", "tablero", "tipoCaso", "usuario", "estadodos", "fase")
                        ->first();

                    $exitoso = $resultado;
                    return null;
                }
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteTipoCasoFormulas($id)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($id, &$error, &$exitoso) {
                $respuestas = TipoCasoFormulas::findOrFail($id);

                $respuestas->delete();

                $exitoso = $respuestas;
                return null;
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}