<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\CampoLikert;
use App\Models\Formulario\FormCampo;
use App\Models\Formulario\FormCampoLikert;
use App\Models\Formulario\FormCampoValor;
use App\Models\Formulario\FormTipoCampo;
use App\Models\Formulario\Formulario;
use App\Models\Formulario\FormValor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CampoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'full',
            'store',
            'add',
            'byId',
            'list',
            'listAll',
            'edit',
            'deleted',
            'restoreById',
            'delete',
            'deleteById'
        ]]);
    }
    public function store()
    {
        try {
            $tipos = FormTipoCampo::all();
            $likertList = FormCampoLikert::all();
            $data = (object) [
                "tipos" => $tipos,
                "likertList" => $likertList
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function list()
    {
        try {
            $data = FormCampo::all();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function listAll()
    {
        try {
            $data = FormCampo::withTrashed()->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function full($id)
    {
        try {
            $empleado = FormCampo::with('valor')->find($id);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito.', $empleado));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function add(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                $dataCampo = $request->all();
                $newCampo = FormCampo::create($dataCampo);
                $newCampo->nombre = 'FORMCAMPO'.$newCampo->id;
                $newCampo->form_control_name = 'FORMCAMPO' . $newCampo->id;
                $newCampo->save();
                if ($newCampo->tipo_campo_id == 2) {
                    $camposLikers = FormCampoLikert::all();
                    foreach ($camposLikers as $camp) {
                        CampoLikert::create([
                            "campo_id" => $newCampo->id,
                            "fcl_id" => $camp->id
                        ]);
                    }
                }
                $this->reordenarCampos($newCampo);
                $formularioController = new FormController();
                return $formularioController->obtenerFormularioCompleto($newCampo->form_id);
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }

    public function addCampoValor(Request $request)
    {
        try {
            $userId = Auth::id();
            DB::transaction(function () use ($request, $userId) {
                $valor = $request->all();
                $campoId = $request->input('campoId');
                $valor['user_id'] = $userId;

                if ($request->input('id')) {
                    $modificarCampo = FormValor::find($valor['id']);
                    $modificarCampo->update($valor);
                    return FormValor::find($valor['id']);
                } else {
                    $newValor = FormValor::create($valor);
                    $newCampoValor = FormCampoValor::create([
                        "valor_id" => $newValor->id,
                        "campo_id" => $campoId
                    ]);
                    return FormValor::find($newValor->id);
                }
            });
            $data = Formulario::with([
                'campo.tipo',
                'campo.likert',
                'campo.valor' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                },
            ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }

    public function edit(Request $request, $id)
    {

        try {
            $data = DB::transaction(function () use ($request, $id) {
                $campoData = $request->all();
                $campo = FormCampo::findOrFail($id);
                $campo->update($campoData);
                $this->reordenarCamposEditados($campo->form_id, $campo);
                $formularioController = new FormController();
                return $formularioController->obtenerFormularioCompleto($campo->form_id);
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
    public function deleteById($id)
    {
        try {
            $data = FormCampo::find($id);
            if ($data) {
                $data->delete();
                $this->reordenarCamposEliminados($data->form_id, $data);
                //$result = Formulario::with('campo.tipo', 'campo.likert')->find($data->form_id);

                $formularioController = new FormController();

                $result = $formularioController->obtenerFormularioCompleto($data->form_id);

                return response()->json(RespuestaApi::returnResultado('success', 'Eliminado con éxito.', $result));
            }
            return response()->json(RespuestaApi::returnResultado('error', 'No existe', $id));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function restoreById($id)
    {
        try {
            $data = FormCampo::withTrashed()->find($id);
            //$data->restore();
            $this->restaurarCampo($data->form_id, $data);
            $formularioController = new FormController();

            $result = $formularioController->obtenerFormularioCompleto($data->form_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Restaurado con éxito.', $result));
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function byId($id)
    {
        try {
            $data = FormCampo::find($id);
            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'El id no existe', $id));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }


    public function reordenarCampos($newCampo)
    {
        // Obtener los campos ordenados por 'orden' de manera ascendente
        $campos = FormCampo::whereNull('deleted_at')
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




    public function reordenarCamposEliminados($formId, $campoEliminado)
    {
        // Obtener los campos ordenados por 'orden' de manera ascendente
        $campos = FormCampo::whereNull('deleted_at')
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
        $campos = FormCampo::whereNull('deleted_at')
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
        $camposRestantes = FormCampo::where('form_id', $formId)
            // ->where('orden', '>=', $campoEliminado->orden)
            // ->where('id', '>=', $campoEliminado->id)
            ->orderBy('orden', 'asc')
            ->get();
        // Reajustar el orden de los campos restantes
        // Restaurar el campo eliminado
        foreach ($camposRestantes as $index => $campo) {
            $campo->orden = $index + 1;
            $campo->save();
        }
        // $campoEliminado->orden = $ordenTemporal;
        // $campoEliminado->save();
    }











    public function restaurarCampoManual($formId, $campoEliminado)
    {
        echo ('$formId: ' . json_encode($formId));
        $ordenTemporal = $campoEliminado->orden;
        // Obtener los campos eliminados lógicamente después del campo restaurado
        $camposRestantes = FormCampo::withTrashed()
            ->select('id', 'nombre', 'orden', 'deleted_at')
            ->where('form_id', $formId)
            ->where('orden', '>=', $campoEliminado->orden)
            ->where('id', '>=', $campoEliminado->id)
            ->orderBy('orden', 'asc')
            ->get();

        echo ('$camposRestantes: ' . json_encode($camposRestantes));

        return;
        // Reajustar el orden de los campos restantes
        // Restaurar el campo eliminado
        $campoEliminado->restore();
        foreach ($camposRestantes as $index => $campo) {
            $campo->orden = $campo->orden + $index;
            $campo->save();
        }
        $campoEliminado->orden = $ordenTemporal;
        $campoEliminado->save();
    }
}
