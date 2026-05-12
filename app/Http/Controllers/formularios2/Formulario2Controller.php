<?php

namespace App\Http\Controllers\formularios2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\formularios2\CForm;
use App\Models\crm\formularios2\DForm;
use App\Models\crm\formularios2\Form;
use App\Models\crm\formularios2\Field;
use App\Models\crm\formularios2\Seccion;
use App\Models\crm\TipoCaso;
use App\Models\crm\TipoCasoFormulas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class Formulario2Controller extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api', ['except' => ['listAllClientesDynamo']]);
    }

    public function listAll()
    // Este metodo se una para listar todos los formularios en el CRUD de formularios dinamicos
    {
        try {
            // Excluimos el id 1 de la tabla crm.formularios, porque el id 1 es nuestro default
            $data = Form::where('id', '!=', 1)
                ->with([
                    'seccion' => function ($query) {
                        // Ordena las secciones por el atributo 'orden'
                        $query->orderBy('order', 'asc');
                    },
                    'seccion.campo' => function ($query) {
                        // Ordena los campos dentro de cada sección por el atributo 'orden'
                        $query->orderBy('order', 'asc');
                    }
                ])
                ->with('formUser.usuario')
                ->orderBy('id', 'asc') // Esto ordena los formularios por el ID
                ->get();

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
                    'form' => function ($query) {
                        $query->where('isactive', true);
                    },
                    'form.seccion' => function ($query) {
                        $query->where('isactive', true)->orderBy('order', 'asc');
                    },
                    'form.seccion.campo' => function ($query) {
                        $query->where('isactive', true)->orderBy('order', 'asc');
                    }
                ])
                ->first();

            if ($data->form) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Formulario inactivo, por favor, comuniquese con el administrador.', ''));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function deleteFormulario(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $formulario = Form::findOrFail($id);

                if ($formulario->image || $formulario->image_company) {
                    $parametro = DB::table('crm.parametro')
                        ->where('abreviacion', 'NAS')
                        ->first();
                }

                if ($formulario->image) {
                    if ($parametro->nas == true) {
                        Storage::disk('nas')->delete($formulario->image); //Mandamos a borrar la foto de nuestra carpeta NAS
                    } else {
                        Storage::disk('local')->delete($formulario->image); //Mandamos a borrar la foto de nuestra carpeta storage
                    }
                }

                if ($formulario->image_company) {
                    if ($parametro->nas == true) {
                        Storage::disk('nas')->delete($formulario->image_company); //Mandamos a borrar la foto de nuestra carpeta NAS
                    } else {
                        Storage::disk('local')->delete($formulario->image_company); //Mandamos a borrar la foto de nuestra carpeta storage
                    }
                }

                // Verificar si existe el array de secciones y tiene elementos
                if (!empty($formulario->secciones)) {
                    // Eliminar campos asociados a las secciones del formulario
                    foreach ($formulario->secciones as $seccion) {
                        // Eliminar los campos asociados a la sección
                        Field::where('seccion_id', $seccion->id)->delete();

                        // Eliminar la sección
                        Seccion::where('id', $seccion->id)->delete();
                    }
                }

                $formulario->delete();
                return Form::where('id', '!=', 1)->with('seccion.campo')->orderBy('id', 'asc')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAllSinRelacion()
    // Este metodo se una para listar todos los formularios en el CRUD de 'tipo de caso'
    {
        try {
            // Excluimos el id 1 de la tabla crm.formularios, porque el id 1 es nuestro default
            $data = Form::orderByDesc('id')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function listCFormulario($cform_id)
    {
        try {
            $data = CForm::where('id', $cform_id)
                ->with([
                    'form.seccion' => function ($query) {
                        // Ordenar las secciones por el atributo 'orden'
                        $query->where('isactive', true)->orderBy('order', 'asc');
                    },
                    'form.seccion.campo' => function ($query) {
                        // Ordenar los campos por el atributo 'orden'
                        $query->where('isactive', true)->orderBy('order', 'asc');
                    },
                    'form.seccion.campo.dform' => function ($query) use ($cform_id) {
                        // Filtrar las respuestas por 'cform_id' y ordenarlas por 'id'
                        $query->where('cform_id', $cform_id)->orderBy('id', 'asc');
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

                $campoRespuesta = DForm::findOrFail($id);

                $campoRespuesta->update($request->all());

                return $campoRespuesta;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // jgsj endPoint que se utiliza para la llamada del cliente en los formularios 2
    public function listCliente_byIdentificacion($identificacion)
    {
        try {
            // $data = DB::selectOne("SELECT cli_id, ent_id, ent_identificacion, ent_nombre_comercial, ent_nombre, ent_apellidos, direccion, calle_secundaria, numero_casa, telefono_domicilio, prv_nombre, ctn_nombre, ent_email FROM public.aav_migracion_cliente where ent_identificacion = '$identificacion'");
            $data = DB::selectOne("SELECT * FROM crm.av_cliente_completo where identificacion = '$identificacion'");

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no existe con la identificación ' . $identificacion, ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Este listar se llama en el formulario externo
    public function listFormulario2($id)
    {
        try {
            $data = Form::where('id', $id)->where('isactive', true)
                ->with([
                    'seccion' => function ($query) {
                        // Filtra las secciones donde el estado sea true y ordena por 'orden'
                        $query->where('isactive', true)->orderBy('order', 'asc');
                    },
                    'seccion.campo' => function ($query) {
                        // Filtra los campos donde el estado sea true y ordena por 'orden'
                        $query->where('isactive', true)->orderBy('order', 'asc');
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
                // Decodificar formulario
                $formulario = json_decode($request->input('formulario'), true);
                $campos_eliminados = json_decode($request->input('campos_eliminados'), true);
                $secciones_eliminados = json_decode($request->input('secciones_eliminados'), true);

                // Crear o actualizar el formulario
                $formularioGuardado = Form::updateOrCreate(
                    ['id' => $formulario['id']],
                    $formulario
                );

                if ($request->hasFile('imagen_file') || $request->hasFile('imagen_file2')) {
                    $parametro = DB::table('crm.parametro')
                        ->where('abreviacion', 'NAS')
                        ->first();
                }

                // Pregunto si existe
                if ($request->hasFile('imagen_file')) {

                    if ($formularioGuardado->image) {
                        if ($parametro->nas == true) {
                            // Eliminamos la imagen anterior del NAS
                            Storage::disk('nas')->delete($formularioGuardado->image);
                        } else {
                            // Eliminamos la imagen anterior local
                            Storage::disk('local')->delete($formularioGuardado->image);
                        }
                    }

                    $imagen = $request->file('imagen_file');
                    $titulo = str_replace(' ', '-', $imagen->getClientOriginalName()); // reemplaza los espacios por un -

                    // Fecha actual
                    $fechaActual = Carbon::now()->format('Y-m-d');

                    // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                    $fecha_actual = str_replace(':', '-', $fechaActual);

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("formularios/formulariosExternos/" . $formularioGuardado->id, $imagen, $formularioGuardado->id . '-' . $fecha_actual . '-' . $titulo);
                    } else {
                        $path = Storage::disk('local')->putFileAs("formularios/formulariosExternos/" . $formularioGuardado->id, $imagen, $formularioGuardado->id . '-' . $fecha_actual . '-' . $titulo);
                    }

                    $formularioGuardado->image = $path; // Aquí obtenemos la ruta de la imagen en la que se encuentra
                    $formularioGuardado->save(); // Aquí guardo la ruta de la imagen actualizada
                }


                if ($request->hasFile('imagen_file2')) { // image del logo de la empresa

                    if ($formularioGuardado->image_company) {
                        if ($parametro->nas == true) {
                            // Eliminamos la imagen anterior del NAS
                            Storage::disk('nas')->delete($formularioGuardado->image_company);
                        } else {
                            // Eliminamos la imagen anterior local
                            Storage::disk('local')->delete($formularioGuardado->image_company);
                        }
                    }

                    $imagen2 = $request->file('imagen_file2');
                    $titulo = str_replace(' ', '-', $imagen2->getClientOriginalName()); // reemplaza los espacios por un -

                    // Fecha actual
                    $fechaActual = Carbon::now()->format('Y-m-d');

                    // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                    $fecha_actual = str_replace(':', '-', $fechaActual);

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("formularios/formulariosExternos/" . $formularioGuardado->id, $imagen2, $formularioGuardado->id . '-' . $fecha_actual . '-' . $titulo);
                    } else {
                        $path = Storage::disk('local')->putFileAs("formularios/formulariosExternos/" . $formularioGuardado->id, $imagen2, $formularioGuardado->id . '-' . $fecha_actual . '-' . $titulo);
                    }

                    $formularioGuardado->image_company = $path; // Aquí obtenemos la ruta de la imagen en la que se encuentra
                    $formularioGuardado->save(); // Aquí guardo la ruta de la imagen actualizada
                }

                // Ahora que tenemos el formulario guardado con un ID, podemos asignar el form_id a las secciones
                foreach ($formulario['seccion'] ?? [] as $seccionData) {
                    // Asegúrate de usar el ID correcto para el formulario guardado
                    if (empty($seccionData['id'])) {
                        // Crear nueva sección si no existe ID
                        $seccion = Seccion::create([
                            'name' => $seccionData['name'],
                            'description' => $seccionData['description'],
                            'order' => $seccionData['order'],
                            'isactive' => $seccionData['isactive'],
                            'form_id' => $formularioGuardado->id, // Asigna el form_id del formulario guardado
                            'margin' => $seccionData['margin'],
                        ]);
                    } else {
                        // Actualizar o crear sección si ya existe ID
                        $seccion = Seccion::updateOrCreate(
                            ['id' => $seccionData['id']], // Buscar por ID
                            $seccionData // Datos para actualizar
                        );
                    }

                    // Si hay campos dentro de la sección, creamos o actualizamos los campos
                    if (isset($seccionData['campo']) && count($seccionData['campo']) > 0) {
                        foreach ($seccionData['campo'] as $campoData) {
                            $campoData['form_id'] = $formularioGuardado->id; // Usar el ID guardado del formulario
                            $campoData['seccion_id'] = $seccion->id; // Asociar el campo con la sección correspondiente

                            // Asegurarse de que opciones_campo sea un string
                            if (isset($campoData['opcion']) && is_array($campoData['opcion'])) {
                                // Si por alguna razón opciones_campo es un array, convertirlo a un string
                                $campoData['opcion'] = implode('ൠ', $campoData['opcion']);
                            }

                            // Si el ID del campo es null, se crea; si ya existe, se actualiza
                            if (empty($campoData['id'])) {
                                // Crear un nuevo campo si no tiene ID
                                $newCampo = Field::create($campoData);
                                $newCampo->form_control_name = str_replace(' ', '', $campoData['label']) . $newCampo->id;
                                $newCampo->save();
                            } else {
                                // Actualizar el campo si ya existe el ID
                                $campoData['form_control_name'] = str_replace(' ', '', $campoData['label']) . $campoData['id'];
                                Field::updateOrCreate(['id' => $campoData['id']], $campoData);
                            }
                        }
                    }
                }

                // Eliminación de campos primero
                if (isset($campos_eliminados) && count($campos_eliminados) > 0) {
                    foreach ($campos_eliminados as $accessDataEliminar) {
                        if (isset($accessDataEliminar['id'])) {
                            Field::where('id', $accessDataEliminar['id'])->delete();
                        }
                    }
                }

                // Eliminación de secciones después de los campos
                if (isset($secciones_eliminados) && count($secciones_eliminados) > 0) {
                    foreach ($secciones_eliminados as $seccionDataEliminar) {
                        if (isset($seccionDataEliminar['id'])) {
                            // Primero asegurarte de que no hay campos asociados a la sección antes de eliminarla
                            Field::where('seccion_id', $seccionDataEliminar['id'])->delete();

                            // Eliminar la sección
                            Seccion::where('id', $seccionDataEliminar['id'])->delete();
                        }
                    }
                }

                // Retornar los formularios guardados, excluyendo el formulario con id 1
                return Form::where('id', '!=', 1)
                    ->with([
                        'seccion' => function ($query) {
                            // Ordena las secciones por el atributo 'orden'
                            $query->orderBy('order', 'asc');
                        },
                        'seccion.campo' => function ($query) {
                            // Ordena los campos dentro de cada sección por el atributo 'orden'
                            $query->orderBy('order', 'asc');
                        }
                    ])
                    ->with('formUser.usuario')
                    ->orderBy('id', 'asc') // Esto ordena los formularios por el ID
                    ->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    private function uploadImage($request, $formulario, $imageField, $imageColumn)
    {
        if ($request->hasFile($imageField)) {
            // Obtener el parámetro NAS
            $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();

            // Eliminar imagen anterior si existe
            if ($formulario->$imageColumn) {
                if ($parametro->nas == true) {
                    // Eliminar imagen del NAS
                    Storage::disk('nas')->delete($formulario->$imageColumn);
                } else {
                    // Eliminar imagen del almacenamiento local
                    Storage::disk('local')->delete($formulario->$imageColumn);
                }
            }

            // Obtener la nueva imagen
            $imagen = $request->file($imageField);
            $titulo = str_replace(' ', '-', $imagen->getClientOriginalName()); // Reemplazar espacios por -

            // Fecha actual para agregar a la imagen
            $fechaActual = Carbon::now()->format('Y-m-d');
            $fecha_actual = str_replace(':', '-', $fechaActual); // Reemplazar ':' para evitar problemas en los nombres de archivo

            // Definir la ruta de almacenamiento
            if ($parametro->nas == true) {
                $path = Storage::disk('nas')->putFileAs("formularios/formulariosExternos/" . $formulario->id, $imagen, $formulario->id . '-' . $fecha_actual . '-' . $titulo);
            } else {
                $path = Storage::disk('local')->putFileAs("formularios/formulariosExternos/" . $formulario->id, $imagen, $formulario->id . '-' . $fecha_actual . '-' . $titulo);
            }

            // Actualizar la columna correspondiente con la nueva ruta de la imagen
            $formulario->$imageColumn = $path;
            $formulario->save();
        }
    }

    public function addCDFormulario(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $input_form_id = json_decode($request->input('form_id'), true);
                $input_dform = json_decode($request->input('dform'), true);

                // echo json_encode($input_dform);

                $dForm_guardado = null;

                // Crear el formulario principal en CFormularios
                $cForm = CForm::create([
                    'form_id' => $input_form_id
                ]);

                // Guardar los datos del formulario en la tabla DFormularios
                if (count($input_dform) > 0) {
                    // Iteramos sobre los campos del formulario
                    foreach ($input_dform as $item) {
                        // Asignamos el id del formulario recién creado
                        $item['cform_id'] = $cForm->id;

                        // Insertamos los datos en la tabla DFormularios
                        $dForm_guardado = DForm::create($item);
                    }
                }

                // Ahora, procesamos los archivos relacionados con este formulario
                $archivos = $request->file('archivos');  // Obtiene los archivos

                if ($archivos) {
                    // Verificamos el parámetro NAS para decidir dónde guardar los archivos
                    $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();

                    // Iteramos sobre los archivos recibidos
                    foreach ($archivos as $archivoData) {
                        $nombreUnico = str_replace(' ', '-', $cForm->id . '-' . $archivoData->getClientOriginalName());
                        $extension = $archivoData->getClientOriginalExtension();

                        // Determinamos la ruta de almacenamiento (local o NAS)
                        $path = Storage::disk($parametro->nas ? 'nas' : 'local')->putFileAs(
                            "formularios/formulariosExternos/" . $cForm->id . '/archivos',
                            $archivoData,
                            $nombreUnico
                        );

                        // Guardar la ruta del archivo en la base de datos (en DFormularios)
                        DForm::create([
                            "cform_id" => $cForm->id,
                            "field_id" => $dForm_guardado->field_id,
                            "value" => $path,  // Ruta del archivo
                            "seccion_id" => $dForm_guardado->seccion_id,
                        ]);
                    }
                }
                // else {
                //     echo ('no hay archivos para guardar....');
                // }

                // Retornamos el ID del formulario recién creado
                return $cForm->id;
            });

            // Respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, capturamos la excepción y respondemos con el error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listFormulaByIdForm($form_id)
    {
        try {
            // $data = TipoCasoFormulas::with('tipoCaso')->get();

            $data = TipoCasoFormulas::with('tipoCaso')
                ->whereHas('tipoCaso', function ($query) {
                    // Filtramos para que tipo_caso.form_id sea diferente de 1 (1 es del default de los formularios)
                    $query->where('form_id', '!=', 1);
                })
                ->get();
            // echo ($data);

            // Filtramos aquellos que tengan un tipo_caso con el form_id especificado
            $filtered = array_filter($data->toArray(), function ($formulario) use ($form_id) {
                return $formulario['tipo_caso']['form_id'] == $form_id;
            });

            if (!empty($filtered)) {
                // Devolvemos el primer objeto
                $filteredItem = reset($filtered); // reset() devuelve el primer valor del array filtrado
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $filteredItem));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Este formulario no tiene una fórmula asignada.', ''));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function listFacturasPendientes($fechaInicio, $fechaFin)
    {
        try {
            // $data = DB::select('SELECT * FROM crm.fn_migracion_cartera_historica_pendiente(?, ?) order by fecha asc', [$fechaInicio, $fechaFin]);
            $data = DB::select('SELECT * FROM crm.fn_migracion_cartera_historica_pendiente_v2(?, ?) order by fecha asc', [$fechaInicio, $fechaFin]);

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron facturas.', ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listFacturasProcesadas($fechaInicio, $fechaFin)
    {
        try {
            // $data = DB::select('SELECT * FROM crm.fn_migracion_cartera_historica_procesada(?, ?) order by cco_caso_id desc', [$fechaInicio, $fechaFin]);
            $data = DB::select('SELECT * FROM crm.fn_migracion_cartera_historica_procesada_v2(?, ?) order by cco_caso_id desc', [$fechaInicio, $fechaFin]);

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron facturas.', ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // lista de clientes de dynamo
    // endPoint para las actividades de un caso
    public function listAllClientesDynamo(Request $request)
    {
        try {
            $page = $request->input('page', 1); // Página actual (por defecto 1)
            $limit = $request->input('limit', 10); // Límite por página (por defecto 10)
            $offset = ($page - 1) * $limit; // Calcular offset

            // Parámetros de búsqueda
            $searchType = $request->input('searchType', ''); // 'identificacion' o 'nombre'
            $searchTerm = $request->input('searchTerm', ''); // Término de búsqueda

            // Construir query base
            $query = DB::table('crm.av_cliente');

            // Aplicar filtros de búsqueda si existen
            if ($searchType && $searchTerm) {
                if ($searchType === 'identificacion') {
                    $query->where('identificacion', 'ILIKE', '%' . $searchTerm . '%');
                } elseif ($searchType === 'nombre') {
                    $query->where('cliente', 'ILIKE', '%' . $searchTerm . '%');
                }
            }

            // Obtener el total de registros (con filtros aplicados)
            $total = $query->count();

            // Obtener los datos paginados
            $data = $query->orderBy('cliente', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No hay clientes', ''));
            }

            // Preparar respuesta con metadata de paginación
            $response = [
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => ceil($total / $limit)
                ]
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $response));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listClienteDynamoByIdentificacion($identificacion)
    {
        try {
            $data = DB::table('crm.av_cliente')
                ->where('identificacion', $identificacion)
                ->first();

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe el cliente', ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function af_obtener_facturas_cliente(Request $request)
    {
        try {
            $data = DB::select('SELECT * FROM crm.af_obtener_facturas_cliente(?, ?)', [$request->identificacion, $request->fecha_caso]);

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron facturas.', ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listProductosActivosDynamo()
    {
        try {
            $data = DB::select("SELECT p.pro_id, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto
                                    FROM producto p
                                    WHERE p.pro_activo = true
                                    ORDER BY p.pro_codigo ASC;");

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron productos.', ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    public function getCasoVacacionesByCasoId($caso_id)
    {
        try {
            $data = DB::selectOne("SELECT a.caso_id, a.tc_id, a.nombre AS nombre_caso, a.identificacion,
                                          a.empleado, a.resultado_caso, a.fecha_fin
                                   FROM crm.av_casos_vacaciones a
                                   WHERE a.caso_id = ?", [$caso_id]);

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'Caso no encontrado.', null));
            }

            if ($data->tc_id == 153) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este caso es una excepción, no es un caso de vacaciones.', null));
            }

            if ($data->resultado_caso == 'RECHAZADO') {
                return response()->json(RespuestaApi::returnResultado('error', 'Este caso ya ha sido rechazado.', null));
            }

            if ($data->resultado_caso !== 'APROBADO') {
                return response()->json(RespuestaApi::returnResultado('error', 'Este caso todavía sigue en proceso.', null));
            }

            if ($data->fecha_fin && $data->fecha_fin < now()->toDateString()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este caso de vacaciones ya finalizó.', null));
            }

            $yaAfectado = DB::selectOne("SELECT caso_id FROM crm.av_casos_vacaciones
                                         WHERE tc_id = 153 AND caso_afectado = ?
                                         LIMIT 1", [$caso_id]);

            if ($yaAfectado) {
                return response()->json(RespuestaApi::returnResultado(
                    'error',
                    'Este caso ya ha sido afectado por la excepción #' . $yaAfectado->caso_id,
                    null
                ));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }
}
