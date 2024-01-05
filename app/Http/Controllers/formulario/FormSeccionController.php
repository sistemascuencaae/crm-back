<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\FormSeccion;
use Illuminate\Http\Request;

class FormSeccionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'add', 'edit', 'formUser', 'listAll'
        ]]);
    }


    public function add(Request $request)
    {
        try {
            $seccionData = $request->all();
            $data = FormSeccion::create($seccionData);
            $this->reordenarCampos($data);
            return response()->json(RespuestaApi::returnResultado('success', 'Guardado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar.', $th));
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $seccionData = $request->all();
            $seccion = FormSeccion::find($id);
            if($seccion){
                $seccion->update($seccionData);
                $this->reordenarCamposEditados($seccion->form_id,$seccion);
                return response()->json(RespuestaApi::returnResultado('success', 'Guardado con éxito.', $seccion));
            }
            return response()->json(RespuestaApi::returnResultado('error', 'El item no existe.', ''));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar.', $th));
        }
    }
    public function reordenarCampos($newCampo)
    {
        // Obtener los campos ordenados por 'orden' de manera ascendente
        $campos = FormSeccion::whereNull('deleted_at')
        ->where('form_id', $newCampo->form_id)
            ->where('id', '<>', $newCampo->id)
            ->orderBy('orden', 'asc')
            ->get();


        // Verificar si ya existe un campo con el mismo orden
        $existingCampo = null;
        foreach ($campos as $campo) {
            if ($campo->orden == $newCampo->orden) {
                $existingCampo = $campo;
                break;
            }
        }

        // // Si existe, incrementar el orden de los campos siguientes
        if ($existingCampo) {
            foreach ($campos as $index => $campo) {
                if ($campo->orden >= $newCampo->orden && $campo->id !== $newCampo->id) {
                    $campo->orden++;
                    $campo->save();
                }
            }
        }
    }

    public function reordenarCamposEliminados($formId)
    {
        // Obtener los campos ordenados por 'orden' de manera ascendente
        $campos = FormSeccion::whereNull('deleted_at')
        ->where('form_id', $formId)
            ->orderBy('orden', 'asc')
            ->get();
        // Recorrer el array para actualizar el campo 'orden' en la base de datos
        foreach ($campos as $index => $campo) {
            // Incrementar el índice ya que 'orden' suele ser 1-indexed
            $nuevoOrden = $index + 1;
            // Actualizar el campo 'orden' en la base de datos
            $campo->update(['orden' => $nuevoOrden]);
        }
    }

    public function reordenarCamposEditados($formId, $campoEditado)
    {
        // Obtener los campos ordenados por 'orden' de manera ascendente
        $campos = FormSeccion::whereNull('deleted_at')
        ->where('form_id', $formId)
            ->orderBy('orden', 'asc')
            ->get();
        // Recorrer el array para actualizar el campo 'orden' en la base de datos
        foreach ($campos as $index => $campo) {
            if ($campo->id == $campoEditado->id) {
            } else {
                // Incrementar el índice ya que 'orden' suele ser 1-indexed
                $nuevoOrden = $index + 1;
                // Actualizar el campo 'orden' en la base de datos
                $campo->update(['orden' => $nuevoOrden]);
            }
        }
    }

    public function restaurarCampo($formId, $campoEliminado)
    {
        $ordenTemporal = $campoEliminado->orden;
        // Obtener los campos eliminados lógicamente después del campo restaurado
        $campoEliminado->restore();
        $camposRestantes = FormSeccion::where('form_id', $formId)
            ->orderBy('orden', 'asc')
            ->get();
        foreach ($camposRestantes as $index => $campo) {
            $campo->orden = $index + 1;
            $campo->save();
        }
    }
}
