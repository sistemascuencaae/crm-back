<?php

namespace App\Http\Controllers\hclinico;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Galeria;
use App\Models\FormOcupacional;
use App\Models\hclinico\FormGaleriaPeriodico;
use App\Models\hclinico\FormPeriodico;
use App\Models\Paciente;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FormPeriodicoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'byIdentificacion', 'edit', 'add'
        ]]);
    }

    public function store($pacId)
    {
        try {
            $paciente = Paciente::where('pac_id', $pacId)->first();
            $ocupacional = FormOcupacional::select(
                    'a_empresa',
                    'a_num_historia_clinica',
                    'a_num_archivo',
                    'a_actividad_puesto_trabajo',
                    'c_anteceden_clinicos_quirur',
                    'e_desc_cardiovascular',
                    'e_desc_metabolica',
                    'e_desc_neurologica',
                    'e_desc_oncologica',
                    'e_desc_infecciosa',
                    'e_desc_hereditaria_congenita',
                    'e_desc_discapacidades',
                    'e_desc_otra',
                    'e_descripcion'
                )
                ->where('pac_id', $pacId)
                ->first();
            $data = (object)[
                "paciente" => $paciente,
                "ocupacional" => $ocupacional,
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th->getMessage()));
        }
    }

    public function add(Request $request){
        try {

            $dataForm = $request->all();


            $formCreado = FormPeriodico::create($dataForm);

            $formActua = FormPeriodico::find($formCreado->fo_per_id);
            if($formActua){
                $formActua->update([
                    "a_num_historia_clinica" => $formCreado->pac_id,
                    "a_num_archivo" => $formCreado->fo_per_id
                ]);
            }

            $data = DB::selectOne("SELECT * FROM hclinico.form_periodico fp
                    inner join hclinico.paciente pac on pac.pac_id = fp.pac_id
                    where fp.fo_per_id = $formCreado->fo_per_id");



            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th->getMessage()));
        }
    }

    public function edit(Request $request, $id)
    {
        try {

            $dataForm = $request->all();

            $formActua = FormPeriodico::find($id);
            if ($formActua) {
                $formActua->update($dataForm);
            }

            $data = DB::selectOne("SELECT * FROM hclinico.form_periodico fp
                    inner join hclinico.paciente pac on pac.pac_id = fp.pac_id
                    where fp.fo_per_id = $id");



            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th->getMessage()));
        }
    }
    public function getFormulario($numeroForm){

        try {
            $data = DB::selectOne("SELECT * FROM hclinico.form_periodico fp
                    inner join hclinico.paciente pac on pac.pac_id = fp.pac_id
                    where fp.fo_per_id = $numeroForm");
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th->getMessage()));
        }
    }


    public function imagenesFormulario($formId)
    {
        try {
            //$data = FormGaleria::with("imagenes")->where('form_id',$formId)->first();

            $data = DB::select("SELECT ga.* from hclinico.form_galeria_periodico fg
                    inner join crm.galerias ga on ga.id = fg.galeria_id
                    where fg.form_id = ?", [$formId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
    public function addGaleriaForm(Request $request, $formId)
    {


        try {
            if ($request->hasFile("imagen_file")) {
                $imagen = $request->file("imagen_file");
                $titulo = $imagen->getClientOriginalName();
                $fechaActual = Carbon::now();
                $fecha_actual = str_replace(':', '-', $fechaActual);
                $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                    ->first();

                if ($parametro->nas == true) {
                    $path = Storage::disk('nas')->putFileAs('formularios/formulario_periodico/' . $formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                } else {
                    $path = Storage::disk('local')->putFileAs('formularios/formulario_periodico/' . $formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                }

                $request->request->add(["imagen" => $path]);
            }

            $galeria = Galeria::create($request->all());
            $ormGaleria = FormGaleriaPeriodico::create([
                "galeria_id" => $galeria->id,
                "form_id" => $formId
            ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {


            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editGaleriaForm(Request $request, $formId)
    {
        try {
            if ($request->hasFile("imagen_file")) {
                $imagen = $request->file("imagen_file");
                $titulo = $imagen->getClientOriginalName();
                $fechaActual = Carbon::now();
                $fecha_actual = str_replace(':', '-', $fechaActual);

                $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                    ->first();

                if ($parametro->nas == true) {
                    $path = Storage::disk('nas')->putFileAs('formularios/formulario_periodico/' . $formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                } else {
                    $path = Storage::disk('local')->putFileAs('formularios/formulario_periodico/' . $formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                }

                $request->request->add(["imagen" => $path]);

            }

            $galeria = Galeria::find($request->input('id'));
            $galeria->update($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editPaciente(Request $request, $id, $formId)
    {
        try {
            $dataPaciente = $request->all();
            $paciente = Paciente::find($id);
            if ($paciente) {
                $paciente->update($dataPaciente);

                $formOcupacional = FormOcupacional::where('pac_id', $id)->first();
                if ($formOcupacional) {
                    if ($request->input('a_empresa')) {
                        $formOcupacional->update([
                            'a_empresa' => $request->input('a_empresa'),
                        ]);
                    }
                    if ($request->input('a_actividad_puesto_trabajo')) {
                        $formOcupacional->update([
                            'a_actividad_puesto_trabajo' => $request->input('a_actividad_puesto_trabajo'),
                        ]);
                    }
                }
                $formPerio = FormPeriodico::where('fo_per_id', $formId)->first();
                if ($formPerio) {
                    if ($request->input('a_empresa')) {
                        $formPerio->update([
                            'a_empresa' => $request->input('a_empresa'),
                        ]);
                    }
                    if ($request->input('a_actividad_puesto_trabajo')) {
                        $formPerio->update([
                            'a_actividad_puesto_trabajo' => $request->input('a_actividad_puesto_trabajo'),
                        ]);
                    }
                }

                $data = DB::selectOne("SELECT * FROM hclinico.form_periodico fp
                    inner join hclinico.paciente pac on pac.pac_id = fp.pac_id
                    where fp.fo_per_id = $formId");
                return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Error al editar.', $id));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear.', $th->getMessage()));
        }
    }
}
