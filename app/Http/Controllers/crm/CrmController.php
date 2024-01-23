<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tablero;
use Exception;

class CrmController extends Controller
{

    public function list($id)
    {
        $log = new Funciones();

        try {
            // $tareas = CTipoTarea::where('tab_id', $tab_id)->with('DTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            $data = Tablero::with(
                'fase',
                'fase.caso',
                'fase.caso',
                'fase.caso.user',
                'fase.caso.tipocaso',
                'fase.caso.clienteCrm',
                'fase.caso.resumen',
                'fase.caso.tareas',
                'fase.caso.miembros.usuario',
                'fase.caso.Actividad',
                'fase.caso.Etiqueta',
                'fase.caso.Galeria',
                'fase.caso.Archivo'
            )->where('id', $id)->first();
            //echo('data: '.json_encode($data));

            $log->logInfo(CrmController::class, 'Se listo con exito las tareas del tablero');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo las tareas del tablero con éxito', $data));
            // } catch (Exception $e) {
            //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
            // }

        } catch (Exception $e) {
            $log->logError(CrmController::class, 'Error al listar las tareas del tablero', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
