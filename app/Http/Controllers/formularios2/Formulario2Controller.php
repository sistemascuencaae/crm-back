<?php

namespace App\Http\Controllers\formularios2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\formularios2\CFormularios;
use App\Models\crm\formularios2\DFormularios;
use App\Models\crm\formularios2\FormularioCampo;
use App\Models\crm\formularios2\Formularios;
use App\Models\crm\TipoCaso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Formulario2Controller extends Controller
{
    public function __construct()
    {
    }

    public function listAll()
    // Este metodo se una para listar todos los formularios en el CRUD de formularios dinamicos
    {
        try {
            // Excluimos el id 1 de la tabla crm.formularios, porque el id 1 es nuestro default
            $data = Formularios::where('id', '!=', 1)->with('formulario_campo')->orderBy('id', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function listFormByTipoCaso($tipoCaso_id)
    {
        try {
            $data = TipoCaso::where('id', $tipoCaso_id)
                ->with([
                    'formularios.formulario_campo' => function ($query) {
                        $query->orderBy('id', 'asc');  // Ordena ASC los campos por 'id'
                    }
                ])
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function addEditFormulario(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                // actualizamos o creamos un formulario
                $formulario = Formularios::updateOrCreate(
                    ['id' => $request->formulario['id']], // Si el id existe, lo actualiza, si no lo crea
                    $request->formulario
                );


                if (count($request->formulario_campo) > 0) {
                    // Iteramos sobre los campos del formulario
                    foreach ($request->formulario_campo as $accessData) {
                        // Asignamos el id del formulario al campo
                        $accessData['form_id'] = $formulario->id;

                        // Si el campo tiene id, lo actualizamos, si no lo creamos
                        if ($accessData['id']) {
                            $accessData['form_control_name'] = $accessData['tipo'] . $accessData['id'];
                            FormularioCampo::where('id', $accessData['id'])->update($accessData);
                        } else {
                            $newCampo = FormularioCampo::create($accessData); // Crear si no tiene id
                            $newCampo->form_control_name = $accessData['tipo'] . $newCampo->id;
                            $newCampo->save();
                        }
                    }
                }


                // Código de eliminación de campos
                if (isset($request->formulario_campo_eliminados) && count($request->formulario_campo_eliminados) > 0) {
                    foreach ($request->formulario_campo_eliminados as $accessDataEliminar) {
                        if (isset($accessDataEliminar['id'])) { // conprueba si tiene un id para eliminar
                            FormularioCampo::where('id', $accessDataEliminar['id'])->delete();
                        }
                    }
                }


                // Retornamos la lista de formularios con sus campos
                return Formularios::where('id', '!=', 1)->with('formulario_campo')->orderBy('id', 'asc')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteFormulario(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $formulario = Formularios::findOrFail($id);

                $formulario->delete();
                return Formularios::where('id', '!=', 1)->with('formulario_campo')->orderByDesc('id')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAllSinRelacion()
    // Este metodo se una para listar todos los formularios en el CRUD de 'tipo de caso'
    // no trae la relacion formulario_campo y es mas liviado la consulta a la base
    {
        try {
            // Excluimos el id 1 de la tabla crm.formularios, porque el id 1 es nuestro default
            $data = Formularios::orderByDesc('id')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function addCDFormulario(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $cFormulario = CFormularios::create($request->all());

                // aqui es donmde yo debo de obtener el cform_id para guardar en el DFormulario

                if (count($request->dFormulario) > 0) {
                    // Iteramos sobre los campos del formulario
                    foreach ($request->dFormulario as $item) {
                        // Asignamos el id del formulario al campo
                        $item['cform_id'] = $cFormulario->id;

                        DFormularios::create($item); // Crear si no tiene id
                    }
                }

                return $cFormulario->id;
            });

            // return response()->json($data);
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listCFormulario($form_id)
    {
        try {
            $data = CFormularios::where('id', $form_id)
                ->with([
                    'formulario.formulario_campo.respuesta' => function ($query) {
                        $query->orderBy('id', 'asc');  // Ordena ASC los campos por 'id'
                    }
                ])
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function editValorRespuesta(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {

                $campoRespuesta = DFormularios::findOrFail($id);

                $campoRespuesta->update($request->all());

                return $campoRespuesta;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // juan endPoint que se utiliza para la llamada del cliente en los formularios 2
    public function listCliente_byIdentificacion($identificacion)
    {
        try {
            $data = DB::selectOne("SELECT cli_id, ent_id, ent_identificacion, ent_nombre_comercial FROM public.aav_migracion_cliente where ent_identificacion = '$identificacion'");

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no existe con la identificación ' . $identificacion, ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listFormulario2($id){
        try {
            $data = Formularios::where('id', $id)
                ->with([
                    'formulario_campo' => function ($query) {
                        $query->orderBy('id', 'asc');  // Ordena ASC los campos por 'id'
                    }
                ])
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

}
