<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Requerimientos;
use Exception;
use Illuminate\Http\Request;

class RequerimientoController extends Controller
{

    public function listRequerimientosByFaseId($fase_id)
    {
        try {
            $requerimientos = Requerimientos::where('fase_id', $fase_id)->orderBy('orden', 'ASC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimientos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addRequerimientos(Request $request)
    {
        try {
            $existeReqEquifax = Requerimientos::where('fase_id', $request->input('fase_id'))->where('tipo', 'equifax')->first();
            if($existeReqEquifax){
                // Si ya existe un requerimiento con tipo "equifax" en esta fase
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de EQUIFAX: ' . $existeReqEquifax->nombre, ''));
            }
            // Verifica si ya existe un requerimiento con el tipo "perfil de cliente" en la misma fase
            $existingRequerimiento = Requerimientos::where('fase_id', $request->input('fase_id'))
                ->where('tipo', 'perfil de cliente')
                ->first();

            if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
                // Si ya existe un requerimiento con tipo "perfil de cliente" en esta fase
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
            } else {
                // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
                $requerimiento = Requerimientos::create($request->all());

                $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->orderBy('orden', 'ASC')->get();

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addRequerimientos(Request $request)
    // {
    //     try {
    //         // Verifica si ya existe un requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('tipo', 'perfil de cliente')
    //             ->first();

    //         // Verifica si ya existe un requerimiento con el mismo valor en el campo "orden"
    //         $existingOrden = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('orden', $request->input('orden'))
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             // Si ya existe un requerimiento con tipo "perfil de cliente" en esta fase
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } elseif ($existingOrden) {
    //             // Si ya existe un requerimiento con el mismo valor en el campo "orden"
    //             return response()->json(RespuestaApi::returnResultado('error', 'El campo "orden" ya existe en otro registro.', ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento = Requerimientos::create($request->all());

    //             $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->orderBy('orden', 'ASC')->get();

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
    //         }

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function editRequerimientos(Request $request, $id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            // Verifica si ya existe otro requerimiento con el tipo "perfil de cliente" en la misma fase
            $existingRequerimiento = Requerimientos::where('fase_id', $requerimiento->fase_id)
                ->where('tipo', 'perfil de cliente')
                ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
                ->first();

            if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
                return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
            } else {

                $requerimiento->update($request->all());

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $requerimiento));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function editRequerimientos(Request $request, $id)
    // {
    //     try {
    //         $requerimiento = Requerimientos::findOrFail($id);

    //         // Verifica si ya existe otro requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('tipo', 'perfil de cliente')
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         // Verifica si ya existe un requerimiento con el mismo valor en el campo "orden"
    //         $existingOrden = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('orden', $request->input('orden'))
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } elseif ($existingOrden) {
    //             // Si ya existe un requerimiento con el mismo valor en el campo "orden"
    //             return response()->json(RespuestaApi::returnResultado('error', 'El campo "orden" ya existe en otro registro.', ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento->update($request->all());

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $requerimiento));
    //         }
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


    public function deleteRequerimientos($id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            $requerimiento->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $requerimiento));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
