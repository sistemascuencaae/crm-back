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
use App\Models\Formulario\Parametro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CampoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'store',
            'list',
            'listAll',
            'full',
            'add',
            'listAll',
            'addCampoValor1',
            'addCampoValor',
            'restoreById',
            'restoreById',
            'deleteById',
            'deleteById',
            'edit'
        ]]);
    }
    public function store()
    {
        try {
            $tipos = FormTipoCampo::all();
            $likertList = FormCampoLikert::all();
            //$parametros = Parametro::with('parametrosHijos')->get();
            $data = (object) [
                "tipos" => $tipos,
                "likertList" => $likertList,
                //"parametros" => $parametros
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
                $newCampo->nombre = 'FORMCAMPO' . $newCampo->id;
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
    public function addCampoValor1(Request $request)
    {
        try {
            $pacId = $request->input('pac_id');
            DB::transaction(function () use ($request, $pacId) {
                $valor = $request->all();
                $campoId = $request->input('campoId');
                $valor['pac_id'] = $pacId;

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
                'campo.valor' => function ($query) use ($pacId) {
                    $query->where('pac_id', $pacId);
                },
            ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
    public function addCampoValor(Request $request)
    {
        try {
            $pacId = $request->input('pac_id');
            $valor = $request->all();
            //echo ('$valor->id: '.json_encode($valor['id']));
            $campoId = $request->input('campoId');
            $valorReal = null;
            if ($request->input('id') !== 0) {
                // Actualizar el registro existente
                $modificarCampo = FormValor::find($valor['id']);
                //echo ('$modificarCampo: '.json_encode($modificarCampo));
                if ($modificarCampo) {
                    $modificarCampo->update($valor);
                }
                $result = FormValor::find($valor['id']);
                $valorReal = $result;
            } else {
                // Crear un nuevo registro
                $newValor = FormValor::create($valor);
                $newCampoValor = FormCampoValor::create([
                    "valor_id" => $newValor->id,
                    "campo_id" => $campoId
                ]);
                $result = FormValor::find($newValor->id);
                $valorReal = $result;
            }

            $campo = FormCampo::find($campoId);
            $data = $this->getNombreControles($campo->form_id, $pacId);
            $seccionesActualizadas = $this->getTotalesSecciones($campo->form_id, $pacId);
            $formController = new FormController();
            $result = (object)[
                "nombresControles" => '', //$data,
                "totalesSecciones" => $seccionesActualizadas,
                "camposImprimir" => $formController->camposImprimir($campo->form_id, $pacId),
                "valorReal" => $valorReal
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Operación realizada con éxito.', $result));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al realizar la operación.', $th->getMessage()));
        }
    }
    public function edit(Request $request, $id)
    {

        try {
            $data = DB::transaction(function () use ($request, $id) {
                $campoData = $request->all();
                $campo = FormCampo::findOrFail($id);
                $campo->update($campoData);
                $this->reordenarCampos($campo);
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
            $data->restore();
            $this->reordenarCampos($data);
            $formularioController = new FormController();
            $result = $formularioController->obtenerFormularioCompleto($data->form_id);
            return response()->json(RespuestaApi::returnResultado('success', 'Restaurado con éxito.', $result));
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
    public function reordenarCampos1($newCampo)
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
            ->where('form_id', $campoEditado->form_id)
            ->where('id', '<>', $campoEditado->id)
            ->orderBy('orden', 'asc')
            ->get();


        // Verificar si ya existe un campo con el mismo orden
        $existingCampo = null;
        foreach ($campos as $campo) {
            if ($campo->orden == $campoEditado->orden) {
                $existingCampo = $campo;
                break;
            }
        }

        // // Si existe, incrementar el orden de los campos siguientes
        if ($existingCampo) {
            foreach ($campos as $index => $campo) {
                if ($campo->orden >= $campoEditado->orden && $campo->id !== $campoEditado->id) {
                    $campo->orden++;
                    echo ('$campo->orden: ' . json_encode($campo->orden));
                    $campo->save();
                }
            }
        }
        // // Obtener los campos ordenados por 'orden' de manera ascendente
        // $campos = FormCampo::whereNull('deleted_at')
        //     ->where('form_id', $formId)
        //     ->orderBy('orden', 'asc')
        //     ->get();
        // // Recorrer el array para actualizar el campo 'orden' en la base de datos
        // foreach ($campos as $index => $campo) {
        //     if ($campo->id == $campoEditado->id) {
        //     } else {
        //         // Incrementar el índice ya que 'orden' suele ser 1-indexed
        //         $nuevoOrden = $index + 1;
        //         // Actualizar el campo 'orden' en la base de datos
        //         $campo->update(['orden' => $nuevoOrden]);
        //     }
        // }
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
        $indexExiste = -1;
        foreach ($campos as $index => $campo) {
            if ($campo->orden == $newCampo->orden && $campo->id !== $newCampo->id) {
                $existingCampo = $campo;
                $indexExiste = $index;
                break;
            }
        }
        if ($indexExiste !== -1 && $existingCampo !== null) {
            $campos->splice($indexExiste, 0, [$newCampo]);
        } else {
            // $campos->push($newCampo);
            $campos->splice($newCampo->orden === 0 ? 1 : $newCampo->orden - 1, 0, [$newCampo]);
        }
        foreach ($campos as $index => $campo) {
            $campo->orden = $index + 1;
            $campo->save();
        }
    }
    public function getNombreControles($formId, $pacId)
    {
        try {
            $data = DB::select("SELECT
                fc.form_control_name,
                CASE
                    WHEN ftc.nombre = 'TEXTO' THEN fv.valor_texto::TEXT
                    WHEN ftc.nombre IN ('LIKERT','NUMERO', 'SELECCION UNICA') THEN fv.valor_entero::text
                    WHEN ftc.nombre IN ('FECHA') THEN fv.valor_date::TEXT
                END AS valor_real
                FROM
                crm.form_campo fc
                LEFT JOIN crm.form_campo_valor fcv ON fcv.campo_id = fc.id
                LEFT JOIN crm.form_valor fv ON fv.id = fcv.valor_id
                left join crm.form_tipo_campo ftc on ftc.id = fc.tipo_campo_id
                WHERE
                fc.form_id = ? and fv.pacId = ? order by fc.orden asc;", [$formId, $pacId]);
            return $data;
        } catch (\Throwable $th) {
            return null;
        }
    }
    public function getTotalesSecciones($formId, $pacId)
    {
        try {
            $data = DB::select("SELECT
	            fc.form_id,
                fv.pac_id,
                fcc.id,
                fcc.orden,
                fcc.nombre as seccion,
                SUM(fcl.puntos) as tu_puntaje,
                CASE
                    -- SECCIONES DE LA BASE DE PRUBAS
                    -- WHEN SUM(fcl.puntos) BETWEEN 13 AND 16 and fcc.id in (8) THEN 'RIESGO BAJO'
                    -- WHEN SUM(fcl.puntos) BETWEEN 8 AND 12 and fcc.id in (7) THEN 'RIESGO MEDIO'
                    -- WHEN SUM(fcl.puntos) BETWEEN 4 AND 7 and fcc.id in (1) THEN 'RIESGO ALTO'

                    -- SECCIONES DE LA BASE DE PRODUCCION
                    WHEN SUM(fcl.puntos) BETWEEN 13 AND 16 and fcc.id in (2,3) THEN 'RIESGO BAJO'
                    WHEN SUM(fcl.puntos) BETWEEN 8 AND 12 and fcc.id in (2,3) THEN 'RIESGO MEDIO'
                    WHEN SUM(fcl.puntos) BETWEEN 4 AND 7 and fcc.id in (2,3) THEN 'RIESGO ALTO'


                    WHEN SUM(fcl.puntos) BETWEEN 18 AND 24 and fcc.id in (4,6) THEN 'RIESGO BAJO'
                    WHEN SUM(fcl.puntos) BETWEEN 12 AND 17 and fcc.id in (4,6) THEN 'RIESGO MEDIO'
                    WHEN SUM(fcl.puntos) BETWEEN 6 AND 11 and fcc.id in (4,6) THEN 'RIESGO ALTO'

		            WHEN SUM(fcl.puntos) BETWEEN 13 AND 16 and fcc.id in (5) THEN 'RIESGO BAJO'
                    WHEN SUM(fcl.puntos) BETWEEN 8 AND 12 and fcc.id in (5) THEN 'RIESGO MEDIO'
                    WHEN SUM(fcl.puntos) BETWEEN 4 AND 7 and fcc.id in (5) THEN 'RIESGO ALTO'

                    WHEN SUM(fcl.puntos) BETWEEN 16 AND 20 and fcc.id in (7,8) THEN 'RIESGO BAJO'
                    WHEN SUM(fcl.puntos) BETWEEN 10 AND 15 and fcc.id in (7,8) THEN 'RIESGO MEDIO'
                    WHEN SUM(fcl.puntos) between 5 AND 9 and fcc.id in (7,8) THEN 'RIESGO ALTO'

		            WHEN SUM(fcl.puntos) BETWEEN 73 AND 96 and fcc.id in (9) THEN 'RIESGO BAJO'
                    WHEN SUM(fcl.puntos) BETWEEN 49 AND 72 and fcc.id in (9) THEN 'RIESGO MEDIO'
                    WHEN SUM(fcl.puntos) between 24 AND 48 and fcc.id in (9) THEN 'RIESGO ALTO'
            ELSE 'RIESGO NO DETERMINADO'
                END AS riesgo
            FROM
                crm.form_campo fc
            left JOIN crm.formulario_seccion fcc ON fcc.id = fc.form_secc_id
            LEFT JOIN crm.form_campo_valor fcv ON fcv.campo_id = fc.id
            LEFT JOIN crm.form_valor fv ON fv.id = fcv.valor_id
            LEFT JOIN crm.form_campo_likert fcl ON fcl.id = fv.valor_entero
            LEFT JOIN crm.form_tipo_campo ftc ON ftc.id = fc.tipo_campo_id
            WHERE
                ftc.id = 2 and fc.form_id = ? and fv.pac_id = ?
            GROUP BY
                1, 2, 3, 4, 5
            ORDER BY 2 ASC;", [$formId, $pacId]);
            return $data;
        } catch (\Throwable $th) {
            return null;
        }
    }
}
