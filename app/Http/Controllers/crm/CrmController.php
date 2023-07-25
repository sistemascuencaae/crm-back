<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tablero;
use Exception;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    //






    public function list($id)
    {
        //try {
            // $tareas = CTipoTarea::where('tab_id', $tab_id)->with('DTipoTarea')->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();
            $data = Tablero::with(
            'fase',
            'fase.caso',
            'fase.caso',
            'fase.caso.user',
            'fase.caso.tipocaso',
            'fase.caso.entidad',
            'fase.caso.resumen',
            'fase.caso.tareas',
            'fase.caso.miembros',
            'fase.caso.Actividad',
            'fase.caso.Etiqueta',
            'fase.caso.Galeria',
            'fase.caso.Archivo'
            )->where('id',$id)->first();
            //echo('data: '.json_encode($data));
          return response()->json(RespuestaApi::returnResultado('success', 'Se listo las tareas del tablero con éxito', $data));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
    }






}
