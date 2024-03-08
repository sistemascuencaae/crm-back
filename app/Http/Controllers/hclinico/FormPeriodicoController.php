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

            // $paciente = Paciente::where('pac_identificacion', $pacId)->first();
            // $ocupacional = DB::selectOne("SELECT
            //     a_empresa,
            //     a_actividad_puesto_trabajo,
            //     c_anteceden_clinicos_quirur,
            //     c_cons_otra_droga,
            //     c_tiempo_cons_otras,
            //     c_cantidad_cons_otras,
            //     c_ex_cons_otras,
            //     c_cons_otra_droga2,
            //     c_tiempo_cons_otras2,
            //     c_cantidad_cons_otras2,
            //     c_ex_cons_otras2,
            //     c_tiempo_cons_tabaco,
            //     c_cantidad_cons_tabaco,
            //     c_tiem_absti_tabaco,
            //     c_cantidad_cons_alcohol,
            //     c_tiempo_cons_alcohol,
            //     c_tiem_abst_alcohol,
            //     c_ex_cons_tabaco,
            //     c_cons_alcohol,
            //     c_ex_cons_alcohol,
            //     c_cons_otras,
            //     c_activi_fisica
            //     c_desc_actifisica,
            //     c_tiemp_actifisica,
            //     c_medicacion_habitual,
            //     c_medicacion_habitual1,
            //     c_tiem_medicacion_habitual1,
            //     c_medicacion_habitual2,
            //     c_tiem_medicacion_habitual2,
            //     c_medicacion_habitual3,
            //     c_tiem_medicacion_habitual3,
            //     c_tiem_abst_otras,
            //     c_tiem_abst_otras2,
            //     d_calificado_sri_acci,
            //     d_especificar_acci,
            //     d_fecha_acci,
            //     d_acci_trabajo_dec,
            //     d_calificado_sri_ep,
            //     d_especificar_ep,
            //     d_fecha_ep,
            //     d_enfe_profesi_dec,
            //     e_desc_cardiovascular
            //     e_desc_metabolica,
            //     e_desc_neurologica,
            //     e_desc_oncologica,
            //     e_desc_infecciosa,
            //     e_desc_hereditaria_congenita,
            //     e_desc_discapacidades,
            //     e_desc_otra,
            //     f_temperaturas_altas1,
            //     f_temperaturas_bajas1,
            //     f_radiacion_ionizante1,
            //     f_radiacion_no_ionizante1,
            //     f_ruido1,
            //     f_vibracion1,
            //     f_iluminacion1,
            //     f_ventilacion1,
            //     f_fluido_electrico1,
            //     f_fisico_otro1,
            //     f_fisico_otro_desc1,
            //     f_atrapa_entre_maquinas1,
            //     f_atrapa_entre_superficies1,
            //     f_atrapa_entre_objetos1,
            //     f_caida_objetos1,
            //     f_aidas_mismo_nivel1,
            //     f_caidas_diferente_nivel1,
            //     f_contacto_electrico1,
            //     f_contacto_superf_trabajos1,
            //     f_proye_particulas_fragm1,
            //     f_proye_fluidos1,
            //     f_pinchazos1,
            //     f_cortes1,
            //     f_tropellamientos_vehiculos1,
            //     f_choques_colision_vehicular1,
            //     f_mecanico_otro1,
            //     f_mecanico_otro_desc1,
            //     f_solidos1,
            //     f_polvos1,
            //     f_humos1,
            //     f_liquidos1,
            //     f_vapoores1,
            //     f_aerosoles1,
            //     f_neblinas1,
            //     f_gaseosos1,
            //     f_quimico_otros1,
            //     f_quimico_otros_desc1,
            //     f_virus1,
            //     f_hongos1,
            //     f_bacterias1,
            //     f_parasitos1,
            //     f_expo_factores1,
            //     f_expo_animselvaticos1,
            //     f_biologico_otro1,
            //     f_biologico_otro_desc1,
            //     f_manejo_manual_cargas1,
            //     f_movimie_repetitivos1,
            //     f_posturas_forzadas1,
            //     f_trabajos_pvd1,
            //     f_ergonomico_otro1,
            //     f_ergonomico_otro_desc1,
            //     f_monot_trabajo1,
            //     f_sobrec_laboral1,
            //     f_minuci_tarea1,
            //     f_alta_responsa1,
            //     f_toma_decisiones1,
            //     f_sed_deficiente1,
            //     f_conflicto_rol1,
            //     f_alta_claridad_funcio1,
            //     f_inco_distrib_trabajo1,
            //     f_turnos_rotativos1,
            //     f_relacio_interp1,
            //     f_inesta_laboral1,
            //     f_psicosocial_otro1,
            //     f_psicosocial_otro_desc1,
            //     f_puestotrabajo1,
            //     f_actividad1,
            //     f_medidas_preventivas1,
            //     f_temperaturas_altas2,
            //     f_temperaturas_bajas2,
            //     f_radiacion_ionizante2,
            //     f_radiacion_no_ionizante2,
            //     f_ruido2,
            //     f_vibracion2,
            //     f_iluminacion2,
            //     f_ventilacion2,
            //     f_fluido_electrico2,
            //     f_fisico_otro2,
            //     f_fisico_otro_desc2,
            //     f_atrapa_entre_maquinas2,
            //     f_atrapa_entre_superficies2,
            //     f_atrapa_entre_objetos2,
            //     f_caida_objetos2,
            //     f_aidas_mismo_nivel2,
            //     f_caidas_diferente_nivel2,
            //     f_contacto_electrico2,
            //     f_contacto_superf_trabajos2,
            //     f_proye_particulas_fragm2,
            //     f_proye_fluidos2,
            //     f_pinchazos2,
            //     f_cortes2,
            //     f_tropellamientos_vehiculos2,
            //     f_choques_colision_vehicular2,
            //     f_mecanico_otro2,
            //     f_mecanico_otro_desc2,
            //     f_solidos2,
            //     f_polvos2,
            //     f_humos2,
            //     f_liquidos2,
            //     f_vapoores2,
            //     f_aerosoles2,
            //     f_neblinas2,
            //     f_gaseosos2,
            //     f_quimico_otros2,
            //     f_quimico_otros_desc2,
            //     f_virus2,
            //     f_hongos2,
            //     f_bacterias2,
            //     f_parasitos2,
            //     f_expo_factores2,
            //     f_expo_animselvaticos2,
            //     f_biologico_otro2,
            //     f_biologico_otro_desc2,
            //     f_manejo_manual_cargas2,
            //     f_movimie_repetitivos2,
            //     f_posturas_forzadas2,
            //     f_trabajos_pvd2,
            //     f_ergonomico_otro2,
            //     f_ergonomico_otro_desc2,
            //     f_monot_trabajo2,
            //     f_sobrec_laboral2,
            //     f_minuci_tarea2,
            //     f_alta_responsa2,
            //     f_toma_decisiones2,
            //     f_sed_deficiente2,
            //     f_conflicto_rol2,
            //     f_alta_claridad_funcio2,
            //     f_inco_distrib_trabajo2,
            //     f_turnos_rotativos2,
            //     f_relacio_interp2,
            //     f_inesta_laboral2,
            //     f_psicosocial_otro2,
            //     f_psicosocial_otro_desc2,
            //     f_puestotrabajo2,
            //     f_actividad2,
            //     f_medidas_preventivas2,
            //     i_piel_anexos,
            //     i_org_sentidos,
            //     i_respiratorio,
            //     i_cardio_vascular,
            //     i_digestivo,
            //     i_genito_urinario,
            //     i_musculo_esqueletico,
            //     i_endocrino,
            //     i_hemolinfatico,
            //     i_nervioso,
            //     i_descripcion,
            //     j_precion_arterial,
            //     j_temperatura,
            //     j_frecuencia_cardiaca,
            //     j_saturacion_oxigeno,
            //     j_frecuencia_respiratoria,
            //     j_peso,
            //     j_talla,
            //     j_indice_masa_corporal,
            //     j_perimetro_abdominal,
            //     k_cicatrices,
            //     k_tatuajes,
            //     k_piel_faneras,
            //     k_parpados,
            //     k_conjuntivas,
            //     k_pupilas,
            //     k_cornea,
            //     k_motilidad,
            //     k_auditivo_externo,
            //     k_pabellon,
            //     k_timpanos,
            //     k_labios,
            //     k_lengua,
            //     k_faringe,
            //     k_amigdalas,
            //     k_dentadura,
            //     k_tabique,
            //     k_cornetes,
            //     k_mucosas,
            //     k_senos_paranasales,
            //     k_tiroides,
            //     k_movilidad,
            //     k_mamas,
            //     k_corazon,
            //     k_pulmones,
            //     k_parrilla_costal,
            //     k_visceras,
            //     k_parde_abdominal,
            //     k_flexibilidad,
            //     k_desviacion,
            //     k_dolor,
            //     k_pelvis,
            //     k_genitales,
            //     k_vascular,
            //     k_mie_superiores,
            //     k_mie_inferiores,
            //     k_fuerza,
            //     k_sensibilidad,
            //     k_marcha,
            //     k_reflejos,
            //     k_observaciones,
            //     m_diagnositico,
            //     m_cie,
            //     m_pre,
            //     m_def,
            //     m_diagnositico2,
            //     m_cie2,
            //     m_pre2,
            //     m_def2,
            //     m_diagnositico3,
            //     m_cie3,
            //     m_pre3,
            //     m_def3,
            //     n_apto,
            //     n_apto_observacion,
            //     n_apto_limitaciones,
            //     n_no_apto,
            //     n_observacion,
            //     n_limitacion,
            //     o_recome_tratamiento,
            //     j_descripcion
            //     from hclinico.formulario_ocupacional fo where fo.pac_id = $pacId");

            // $data = (object)[
            //     "paciente" => $paciente,
            //     "ocupacional" => $ocupacional,
            // ];

            $paciente = Paciente::where('pac_id', $pacId)->first();
            $ocupacional = FormOcupacional::where('pac_id',$pacId)->first();
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
                    $path = Storage::disk('nas')->putFileAs('FormularioPeriodico '.$formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                } else {
                    $path = Storage::disk('local')->putFileAs('FormularioPeriodico ' . $formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
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
                    $path = Storage::disk('nas')->putFileAs('FormularioPeriodico '.$formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                } else {
                    $path = Storage::disk('local')->putFileAs('FormularioPeriodico '.$formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
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
}
