<?php

namespace App\Http\Controllers;

use App\Models\FormAptitudMedica;
use App\Models\FormOcupacional;
use App\Models\Paciente;
use App\Servicios\VariosService;
use App\Servicios\FuncionesReporte;
use \Mpdf\Mpdf as PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReporteFormularios extends Controller
{

	public function documentFAM($pac_id, $fam_id)
	{
		$storageIMG = storage_path('app/images');
		$p = Paciente::find($pac_id);

		// $fo = FormOcupacional::where('pac_id', $p->pac_id)->first();

		$fo = DB::selectOne("SELECT * FROM hclinico.formulario_ocupacional where pac_id = ?", [$pac_id]);

		if ($fo) {


			$fam = FormAptitudMedica::where('fam_id', $fam_id)->first();

			$vs = new VariosService();
			$funcionR = new FuncionesReporte();
			$lateralidad = array_search($p->pac_lateralidad, $vs->vlistaLateralidad());
			$sexo = array_search($p->pac_sexo, $vs->vsexoLista());
			$sangre = array_search($p->pac_grupo_sanguineo, $vs->vtipoSangre());
			$edad = $vs->edad($p->pac_fecha_nacimiento);
			$empresa = array_search($fo->a_empresa, $vs->vempresas());
			$ruc = array_search($fo->a_empresa, $vs->vrucempresa());
			$seccionB = array(
				$funcionR->famChecbox($fam->b_ingreso),
				$funcionR->famChecbox($fam->b_periodico),
				$funcionR->famChecbox($fam->b_reintegro),
				$funcionR->famChecbox($fam->b_retiro),
			);
			$seccionC = $funcionR->famRadioButton($fam->c_aptitud_medica_lavoral);

			$seccionD1sino = $funcionR->famChecboxSINO($fam->d_evalu_retiro, $fam->d_condi_reltra_si);

			$seccionD = array(
				$funcionR->famChecbox($fam->d_condi_presuntiva),
				$funcionR->famChecbox($fam->d_condi_definitiva),
				$funcionR->famChecbox($fam->d_condi_no_aplica),
				$funcionR->famChecbox($fam->d_condi_reltra_noaplica),
			);





			//echo(json_encode($bevaluacion));




			// Setup a filename
			$documentFileName = "fun.pdf";

			// Create the mPDF document
			$document = new PDF([
				'mode' => 'utf-8',
				'format' => 'A4',
				'margin_header' => '3',
				'margin_top' => '20',
				'margin_bottom' => '20',
				'margin_footer' => '2',
				'margin_left' => '6',
				'margin_right' => '6',


			]);

			// Set some header informations for output
			$header = [
				'Content-Type' => 'application/pdf',
				'Content-Disposition' => 'inline; filename="' . $documentFileName . '"'
			];




			$document->WriteHTML('
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>ReporteFAM</title>
        <style type="text/css">
        .Titulo {
        	font-family: Arial, Helvetica, sans-serif;
        	font-size: 11px;
        	font-weight: bold;
        	text-align: center;
        }
        .headerMorado {
        	font-family: Arial, Helvetica, sans-serif;
        	font-size: 11px;
        	font-weight: bold;
        	background-color: #d9d9ff;
        	border: thin solid #000000;
        }
        .HeaderVerde {
        	font-family: Arial, Helvetica, sans-serif;
        	font-size: 11px;
        	font-weight: bold;
        	background-color: #ccffcc;
        }
        .seccionBlanca {
        	font-family: Arial, Helvetica, sans-serif;
        	font-size: 11px;
        	font-weight: normal;
        }
        .ImagenHeader {
        	text-align: center;
        }
        .bordesTabla {
        	border: 1px solid #000000;
        }
        .headerVerdeCentrado {
        	font-family: Arial, Helvetica, sans-serif;
        	font-size: 11px;
        	font-weight: bold;
        	background-color: #ccffcc;
        	text-align: center;
        }
		.titulo {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 14px;
			font-weight: bold;
			color: #0033CC;
		}
		.AlmEsp1 {
			font-size: xx-large;
		}
		.AlmEsp2 {
			color: #FF0000;
			font-size: 28px;
			font-weight: bold;
		}
		.AlmEsp3 {
			color: #006600;
			font-size: 28px;
			font-weight: bold;
		}
        </style>
        <meta name="description" content="REPORTE FORMULARIO APTITUD MEDICA" />
        </head>

        <body>


		<table style="width: 100%">
		<tr>
			<td><span class="AlmEsp3">ALMACENES</span> <span class="AlmEsp2">
			<strong>ESPAÑA</strong></span></td>
			<td class="titulo">APTITUD MEDICA</td>
		</tr>
	</table>





        <table style="width: 100%" class="bordesTabla">
        	<tr>
        		<td colspan="6" class="headerMorado">A. DATOS DEL ESTABLECIMIENTO -
        		EMPRESA Y USUARIO</td>
        	</tr>
        	<tr class="HeaderVerde">
        		<td class="HeaderVerde">EMPRESA</td>
        		<td class="HeaderVerde">RUC</td>
        		<td class="HeaderVerde">CIUU</td>
        		<td class="HeaderVerde">ESTABLECIMIENTO</td>
        		<td class="HeaderVerde">HISTORIA CLINICA</td>
        		<td class="HeaderVerde">ARCHIVO</td>
        	</tr>
        	<tr class="seccionBlanca">
        		<td>' . $empresa . '</td>
        		<td>' . $ruc . '</td>
        		<td>CIUDAD</td>
        		<td>AlmEsp</td>
        		<td>' . $p->pac_id . '</td>
        		<td>' . $fam->fam_id . '</td>
        	</tr>
        	<tr class="HeaderVerde">
        		<td class="HeaderVerde">PRIMER APELLIDO</td>
        		<td class="HeaderVerde">SEGUNDO APELLIDO</td>
        		<td class="HeaderVerde">PRIMER NOMBRE</td>
        		<td class="HeaderVerde">SEGUNDO NOMBRE</td>
        		<td class="HeaderVerde">SEXO</td>
        		<td class="HeaderVerde">PUESTO DE TRABAJO</td>
        	</tr>
        	<tr class="seccionBlanca">
        		<td>' . $p->pac_primer_apellido . '</td>
        		<td>' . $p->pac_segundo_apellido . '</td>
        		<td>' . $p->pac_primero_nombre . '</td>
        		<td>' . $p->pac_segundo_nombre . '</td>
        		<td>' . $sexo . '</td>
        		<td>' . $fo->a_puesto_trabajo . '</td>
        	</tr>
        </table>
        <table style="width: 100%" class="bordesTabla">
        	<tr>
        		<td colspan="9" class="headerMorado" style="height: 18px">B. DATOS
        		GENERALES</td>
        	</tr>
        	<tr class="seccionBlanca">
        		<td><span class="seccionBlanca">FECHA EMISION</span></td>
        		<td colspan="3">' . $fam->b_fecha_emision . '</td>
        		<td>&nbsp;</td>
        		<td style="width: 65px">&nbsp;</td>
        		<td>&nbsp;</td>
        		<td style="width: 9px">&nbsp;</td>
        		<td>&nbsp;</td>
        	</tr>
        	<tr>
        		<td style="height: 23px"><span class="seccionBlanca">EVALUACION:</span></td>
        		<td style="width: 13px; height: 23px" class="HeaderVerde">INGRESO</td>
        		<td style="height: 23px">' . $seccionB[0] . '</td>
        		<td style="width: 20px; height: 23px" class="HeaderVerde">PERIODICO</td>
        		<td style="height: 23px">' . $seccionB[1] . '</td>
        		<td style="width: 65px; height: 23px" class="HeaderVerde">REINTEGRO</td>
        		<td style="height: 23px">' . $seccionB[2] . '</td>
        		<td style="width: 9px; height: 23px" class="HeaderVerde">RETIRO</td>
        		<td style="height: 23px">' . $seccionB[3] . '</td>
        	</tr>
        </table>
        <table style="width: 100%" class="bordesTabla">
        	<tr>
        		<td colspan="8" class="headerMorado">C. APTITUD MÉDICA LABORAL</td>
        	</tr>
        	<tr>
        		<td colspan="8" class="seccionBlanca">Después de la valoración médica
        		ocupacional se certifica que la persona en mención, es calificada como:</td>
        	</tr>
        	<tr>
        		<td class="HeaderVerde" style="width: 55px">APTO</td>
        		<td class="seccionBlanca">' . $seccionC[0] . '</td>
        		<td class="HeaderVerde" style="width: 141px">APTO EN OBSERVACION</td>
        		<td class="seccionBlanca">' . $seccionC[1] . '</td>
        		<td class="HeaderVerde" style="width: 144px">APTO CON LIMITACIONES</td>
        		<td class="seccionBlanca">' . $seccionC[2] . '</td>
        		<td class="HeaderVerde" style="width: 69px">NO APTO</td>
        		<td class="seccionBlanca">' . $seccionC[3] . '</td>
        	</tr>
        	<tr>
        		<td colspan="8" class="seccionBlanca">' . $fam->c_observaciones . '</td>
        	</tr>
        </table>

        <table style="width: 100%" class="bordesTabla">
        	<tr>
        		<td colspan="7" class="headerMorado">D. EVALUACIÓN MÉDICA DE RETIRO</td>
        	</tr>
        	<tr>
        		<td style="width: 345px" class="seccionBlanca">El usuario se realizó la
        		evaluación médica de retiro</td>
        		<td style="width: 103px" class="HeaderVerde">SI&nbsp;</td>
        		<td style="width: 20px" class="seccionBlanca">' . $seccionD1sino[0] . '</td>
        		<td style="width: 139px" class="HeaderVerde">NO</td>
        		<td style="width: 14px">' . $seccionD1sino[1] . '</td>
        		<td style="width: 91px">&nbsp;</td>
        		<td>&nbsp;</td>
        	</tr>
        	<tr>
        		<td style="width: 345px" class="seccionBlanca">Condición del diagnóstico</td>
        		<td style="width: 103px" class="HeaderVerde">PRESUNTIVA</td>
        		<td style="width: 20px" class="seccionBlanca">' . $seccionD[0] . '</td>
        		<td style="width: 139px" class="HeaderVerde">DEFINITIVA</td>
        		<td style="width: 14px" class="seccionBlanca">' . $seccionD[1] . '</td>
        		<td style="width: 91px" class="HeaderVerde">NO APLICA</td>
        		<td class="seccionBlanca">' . $seccionD[2] . '</td>
        	</tr>
        	<tr>
        		<td style="width: 345px; height: 23px" class="seccionBlanca">La
        		condición de salud esta relacionada con el trabajo </td>
        		<td style="width: 103px; height: 23px" class="HeaderVerde">SI</td>
        		<td style="width: 20px; height: 23px" class="seccionBlanca">' . $seccionD1sino[2] . '</td>
        		<td style="width: 139px; height: 23px" class="HeaderVerde">NO</td>
        		<td style="width: 14px; height: 23px" class="seccionBlanca">' . $seccionD1sino[3] . '</td>
        		<td style="width: 91px; height: 23px" class="HeaderVerde">NO APLICA</td>
        		<td style="height: 23px" class="seccionBlanca">' . $seccionD[3] . '</td>
        	</tr>
        </table>
        <table style="width: 100%" class="bordesTabla">
        	<tr>
        		<td class="headerMorado">E. RECOMENDACIONES </td>
        	</tr>
        	<tr>
        		<td class="seccionBlanca">' . $fam->e_recomendaciones_desc . '</td>
        	</tr>
        </table>
        <p class="HeaderVerde">Con este documento certifico que el trabajador se ha
        sometido a la evaluación médica requerida para (el ingreso /la ejecución/ el
        reintegro y retiro) al puesto laboral y se ha informado sobre los riesgos
        relacionados con el trabajo emitiendo recomendaciones relacionadas con su estado
        de salud.</p>
        <p class="seccionBlanca">La presente certificación se expide con base en la
        historia ocupacional del usuario (a), la cual tiene carácter de confidencial.</p>
        <table style="width: 100%" class="bordesTabla">
        	<tr>
        		<td colspan="6" class="headerMorado">F. DATOS DEL PROFESIONAL DE SALUD</td>
        		<td class="headerMorado">G. FIRMA DEL USUARIO</td>
        	</tr>
        	<tr>
        		<td class="headerVerdeCentrado" style="width: 81px">NOMBRE<br />
        		Y<br />
        		APELLIDO</td>
        		<td style="width: 189px" class="seccionBlanca">PABLO XAVIER MACHUCA CHIRIBOGA</td>
        		<td style="width: 57px" class="headerVerdeCentrado">CODIGO</td>
        		<td style="width: 67px" class="seccionBlanca">' . $p->pac_id . ' ' . $fam->fam_id . '</td>
        		<td class="headerVerdeCentrado">FIRMA Y<br />
        		SELLO</td>
        		<td style="width: 84px">&nbsp;</td>
        		<td>&nbsp;</td>
        	</tr>
        </table>

        </body>

        </html>

        ');

			// Save PDF on your public storage
			Storage::disk('public')->put($documentFileName, $document->Output($documentFileName, "S"));

			// Get file back from storage with the give header informations
			return Storage::disk('public')->download($documentFileName, 'Request', $header); //
		} else {

			// Setup a filename
			$documentFileName = "fun.pdf";

			// Create the mPDF document
			$document = new PDF([
				'mode' => 'utf-8',
				'format' => 'A4',
				'margin_header' => '3',
				'margin_top' => '20',
				'margin_bottom' => '20',
				'margin_footer' => '2',
				'margin_left' => '6',
				'margin_right' => '6',


			]);

			// Set some header informations for output
			$header = [
				'Content-Type' => 'application/pdf',
				'Content-Disposition' => 'inline; filename="' . $documentFileName . '"'
			];

			$document->WriteHTML('
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
	
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title>ReporteFAM</title>
			<style type="text/css">
			
			</style>
			<meta name="description" content="REPORTE FORMULARIO APTITUD MEDICA" />
			</head>
	
			<body>
			No tiene formulario ocupacional
			</body>
		');

			// Save PDF on your public storage
			Storage::disk('public')->put($documentFileName, $document->Output($documentFileName, "S"));

			// Get file back from storage with the give header informations
			return Storage::disk('public')->download($documentFileName, 'Request', $header); //
		}
	}

	public function documentFO($pac_id, $foId)
	{
		$storageIMG = storage_path('app/images');
		$p = Paciente::find($pac_id);
		$fo = FormOcupacional::where('pac_id', $p->pac_id)
			->where('fo_id', $foId)->first();
		$vs = new VariosService();
		$horaModificacion = date("h:j:s", strtotime($fo->updated_at));
		$fechaUpdate = strtotime($fo->updated_at);
		$dia = date("j", $fechaUpdate);
		$mes = date("n", $fechaUpdate);
		$anio = date("Y", $fechaUpdate);
		$fechaModificacion = $dia . '-' . $mes . '-' . $anio;



		$estableSalud = 'ALMACENES ESPAÑA';
		$lateralidad = array_search($p->pac_lateralidad, $vs->vlistaLateralidad());
		$sexo = array_search($p->pac_sexo, $vs->vsexoLista());
		$sangre = array_search($p->pac_grupo_sanguineo, $vs->vtipoSangre());
		$edad = $vs->edad($p->pac_fecha_nacimiento);
		$empresa = array_search($fo->a_empresa, $vs->vempresas());
		$ruc = array_search($fo->a_empresa, $vs->vrucempresa());
		$religion = $vs->vreligion($fo->a_religion, $fo->a_otra_religion);
		$orientacionSexual = $vs->arraySize4($fo->a_orentacion_sexual);
		$identidadGenero = $vs->arraySize4($fo->a_identidad_genero);
		$discapacidad = $vs->arraySize2($fo->a_discapacidad);
		$tipoDiscapacidad = $vs->vtiposDiscapacidad($fo->a_tipo_discapacidad, $fo->a_otra_discapacidad);
		$sqlDepart = 'select dep_nombre from hclinico.departamento where dep_id = ' . $fo->dep_id . '  order by dep_nombre';
		$departamentos = DB::select($sqlDepart)[0]->dep_nombre;
		$sqlCargo = 'select * from hclinico.cargo where car_id = ' . $fo->car_id . ' order by car_nombre';
		$cargo = DB::select($sqlCargo)[0]->car_nombre;
		$sqlCiudades = "select * from public.ciudad where ciu_id = " . $p->ciudad_id;
		$ciudad = DB::select($sqlCiudades)[0]->ciu_nombre;
		$vidaSexuActiva = $vs->arraySize2($fo->c_vida_sexual_activa);
		$metodoPlani = $vs->arraySize2($fo->c_metodo_pla_familiar);
		$exPapani = $vs->arraySize2($fo->c_exam_papanicolao);
		$exEcoma = $vs->arraySize2($fo->c_exam_ecomamario);
		$exMamog = $vs->arraySize2($fo->c_exam_mamografia);
		$exColo = $vs->arraySize2($fo->c_exam_colposcopia);
		$exAntPro = $vs->arraySize2($fo->c_exam_antigeno_prostatico);
		$exEcoPro = $vs->arraySize2($fo->c_exam_ecoprostatico);
		$conTabac = $vs->arraySize2($fo->c_cons_tabaco);
		$exConsuTaba = $vs->arraySize2($fo->c_ex_cons_tabaco);
		$exConsuAlco = $vs->arraySize2($fo->c_ex_cons_tabaco);
		$conAlcho = $vs->arraySize2($fo->c_ex_cons_alcohol);
		$conOtrDro1 = $vs->arraySize2($fo->c_cons_otras);
		$conOtrDro2 = $vs->arraySize2($fo->c_cons_otra_droga2);
		$exConOtra1 = $vs->arraySize2($fo->c_ex_cons_otras);
		$exConOtra2 = $vs->arraySize2($fo->c_ex_cons_otras2);
		$actiFisica = $vs->arraySize2($fo->c_activi_fisica);
		$mediHabitu = $vs->arraySize2($fo->c_medicacion_habitual);
		$riesgofisic = $vs->arraySize2($fo->d_riesgo_fisico);
		$riesgomecan = $vs->arraySize2($fo->d_riesgo_mecanico);
		$riesgoquimi = $vs->arraySize2($fo->d_riesgo_quimico);
		$riesgobiolo = $vs->arraySize2($fo->d_riesgo_biologico);
		$riesgoergon = $vs->arraySize2($fo->d_riesgo_ergonomico);
		$riesgopsico = $vs->arraySize2($fo->d_riesgo_psicosocial);
		$riesgofisic2 = $vs->arraySize2($fo->d_riesgo_fisico2);
		$riesgomecan2 = $vs->arraySize2($fo->d_riesgo_mecanico2);
		$riesgoquimi2 = $vs->arraySize2($fo->d_riesgo_quimico2);
		$riesgobiolo2 = $vs->arraySize2($fo->d_riesgo_biologico2);
		$riesgoergon2 = $vs->arraySize2($fo->d_riesgo_ergonomico2);
		$riesgopsico2 = $vs->arraySize2($fo->d_riesgo_psicosocial2);
		$riesgofisic3 = $vs->arraySize2($fo->d_riesgo_fisico3);
		$riesgomecan3 = $vs->arraySize2($fo->d_riesgo_mecanico3);
		$riesgoquimi3 = $vs->arraySize2($fo->d_riesgo_quimico3);
		$riesgobiolo3 = $vs->arraySize2($fo->d_riesgo_biologico3);
		$riesgoergon3 = $vs->arraySize2($fo->d_riesgo_ergonomico3);
		$riesgopsico3 = $vs->arraySize2($fo->d_riesgo_psicosocial3);
		$riesgofisic4 = $vs->arraySize2($fo->d_riesgo_fisico4);
		$riesgomecan4 = $vs->arraySize2($fo->d_riesgo_mecanico4);
		$riesgoquimi4 = $vs->arraySize2($fo->d_riesgo_quimico4);
		$riesgobiolo4 = $vs->arraySize2($fo->d_riesgo_biologico4);
		$riesgoergon4 = $vs->arraySize2($fo->d_riesgo_ergonomico4);
		$riesgopsico4 = $vs->arraySize2($fo->d_riesgo_psicosocial4);
		$califiSriAcc = $vs->arraySize2($fo->d_calificado_sri_acci);
		$califiSriEp = $vs->arraySize2($fo->d_calificado_sri_ep);
		$edecariovas = $vs->arraySize2($fo->e_desc_cardiovascular);
		$edemetbolic = $vs->arraySize2($fo->e_desc_metabolica);
		$edeneuologi = $vs->arraySize2($fo->e_desc_neurologica);
		$edeonclogic = $vs->arraySize2($fo->e_desc_oncologica);
		$edeinfccios = $vs->arraySize2($fo->e_desc_infecciosa);
		$edeherditar = $vs->arraySize2($fo->e_desc_hereditaria_congenita);
		$ededisapaci = $vs->arraySize2($fo->e_desc_discapacidades);
		$edecrisotra = $vs->arraySize2($fo->e_desc_otra);
		$ftempeb1 = $vs->arraySize2($fo->f_temperaturas_altas1);
		$ftempea1 = $vs->arraySize2($fo->f_temperaturas_bajas1);
		$fradiae1 = $vs->arraySize2($fo->f_radiacion_ionizante1);
		$fradiac1 = $vs->arraySize2($fo->f_radiacion_no_ionizante1);
		$fruidoa1 = $vs->arraySize2($fo->f_ruido1);
		$fvibrac1 = $vs->arraySize2($fo->f_vibracion1);
		$filumin1 = $vs->arraySize2($fo->f_iluminacion1);
		$fventil1 = $vs->arraySize2($fo->f_ventilacion1);
		$ffluido1 = $vs->arraySize2($fo->f_fluido_electrico1);
		$ffisico1 = $vs->arraySize2($fo->f_fisico_otro1);
		$fatrape1 = $vs->arraySize2($fo->f_atrapa_entre_maquinas1);
		$fatrapa1 = $vs->arraySize2($fo->f_atrapa_entre_superficies1);
		$fatrapi1 = $vs->arraySize2($fo->f_atrapa_entre_objetos1);
		$fcaidaa1 = $vs->arraySize2($fo->f_caida_objetos1);
		$faidasa1 = $vs->arraySize2($fo->f_aidas_mismo_nivel1);
		$fcaidas1 = $vs->arraySize2($fo->f_caidas_diferente_nivel1);
		$fcontac1 = $vs->arraySize2($fo->f_contacto_electrico1);
		$fcontab1 = $vs->arraySize2($fo->f_contacto_superf_trabajos1);
		$fproyea1 = $vs->arraySize2($fo->f_proye_particulas_fragm1);
		$fproyeb1 = $vs->arraySize2($fo->f_proye_fluidos1);
		$fpincha1 = $vs->arraySize2($fo->f_pinchazos1);
		$fcortes1 = $vs->arraySize2($fo->f_cortes1);
		$ftropel1 = $vs->arraySize2($fo->f_tropellamientos_vehiculos1);
		$fchoque1 = $vs->arraySize2($fo->f_choques_colision_vehicular1);
		$ftempeb2 = $vs->arraySize2($fo->f_temperaturas_altas2);
		$ftempea2 = $vs->arraySize2($fo->f_temperaturas_bajas2);
		$fradiae2 = $vs->arraySize2($fo->f_radiacion_ionizante2);
		$fradiac2 = $vs->arraySize2($fo->f_radiacion_no_ionizante2);
		$fruidoa2 = $vs->arraySize2($fo->f_ruido2);
		$fvibrac2 = $vs->arraySize2($fo->f_vibracion2);
		$filumin2 = $vs->arraySize2($fo->f_iluminacion2);
		$fventil2 = $vs->arraySize2($fo->f_ventilacion2);
		$ffluido2 = $vs->arraySize2($fo->f_fluido_electrico2);
		$ffisico2 = $vs->arraySize2($fo->f_fisico_otro2);
		$fatrape2 = $vs->arraySize2($fo->f_atrapa_entre_maquinas2);
		$fatrapa2 = $vs->arraySize2($fo->f_atrapa_entre_superficies2);
		$fatrapi2 = $vs->arraySize2($fo->f_atrapa_entre_objetos2);
		$fcaidaa2 = $vs->arraySize2($fo->f_caida_objetos2);
		$faidasa2 = $vs->arraySize2($fo->f_aidas_mismo_nivel2);
		$fcaidas2 = $vs->arraySize2($fo->f_caidas_diferente_nivel2);
		$fcontac2 = $vs->arraySize2($fo->f_contacto_electrico2);
		$fcontab2 = $vs->arraySize2($fo->f_contacto_superf_trabajos2);
		$fproyea2 = $vs->arraySize2($fo->f_proye_particulas_fragm2);
		$fproyeb2 = $vs->arraySize2($fo->f_proye_fluidos2);
		$fpincha2 = $vs->arraySize2($fo->f_pinchazos2);
		$fcortes2 = $vs->arraySize2($fo->f_cortes2);
		$ftropel2 = $vs->arraySize2($fo->f_tropellamientos_vehiculos2);
		$fchoque2 = $vs->arraySize2($fo->f_choques_colision_vehicular2);
		$fsolid1 = $vs->arraySize2($fo->f_solidos1);
		$fpolvo1 = $vs->arraySize2($fo->f_polvos1);
		$fhumos1 = $vs->arraySize2($fo->f_humos1);
		$fliqui1 = $vs->arraySize2($fo->f_liquidos1);
		$fvapoo1 = $vs->arraySize2($fo->f_vapoores1);
		$faeros1 = $vs->arraySize2($fo->f_aerosoles1);
		$fnebli1 = $vs->arraySize2($fo->f_neblinas1);
		$fgaseo1 = $vs->arraySize2($fo->f_gaseosos1);
		$fvirus1 = $vs->arraySize2($fo->f_virus1);
		$fhongo1 = $vs->arraySize2($fo->f_hongos1);
		$fbacte1 = $vs->arraySize2($fo->f_bacterias1);
		$fparas1 = $vs->arraySize2($fo->f_parasitos1);
		$fexpa_1 = $vs->arraySize2($fo->f_expo_factores1);
		$fexpb_1 = $vs->arraySize2($fo->f_expo_animselvaticos1);
		$fmanejo1 = $vs->arraySize2($fo->f_manejo_manual_cargas1);
		$fmovimi1 = $vs->arraySize2($fo->f_movimie_repetitivos1);
		$fpostur1 = $vs->arraySize2($fo->f_posturas_forzadas1);
		$ftrabaj1 = $vs->arraySize2($fo->f_trabajos_pvd1);
		$fsolid2 = $vs->arraySize2($fo->f_solidos2);
		$fpolvo2 = $vs->arraySize2($fo->f_polvos2);
		$fhumos2 = $vs->arraySize2($fo->f_humos2);
		$fliqui2 = $vs->arraySize2($fo->f_liquidos2);
		$fvapoo2 = $vs->arraySize2($fo->f_vapoores2);
		$faeros2 = $vs->arraySize2($fo->f_aerosoles2);
		$fnebli2 = $vs->arraySize2($fo->f_neblinas2);
		$fgaseo2 = $vs->arraySize2($fo->f_gaseosos2);
		$fvirus2 = $vs->arraySize2($fo->f_virus2);
		$fhongo2 = $vs->arraySize2($fo->f_hongos2);
		$fbacte2 = $vs->arraySize2($fo->f_bacterias2);
		$fparas2 = $vs->arraySize2($fo->f_parasitos2);
		$fexpa_2 = $vs->arraySize2($fo->f_expo_factores2);
		$fexpb_2 = $vs->arraySize2($fo->f_expo_animselvaticos2);
		$fmanejo2 = $vs->arraySize2($fo->f_manejo_manual_cargas2);
		$fmovimi2 = $vs->arraySize2($fo->f_movimie_repetitivos2);
		$fpostur2 = $vs->arraySize2($fo->f_posturas_forzadas2);
		$ftrabaj2 = $vs->arraySize2($fo->f_trabajos_pvd2);
		$f_monot1 = $vs->arraySize2($fo->f_monot_trabajo1);
		$f_sobre1 = $vs->arraySize2($fo->f_sobrec_laboral1);
		$f_minuc1 = $vs->arraySize2($fo->f_minuci_tarea1);
		$f_alta_1 = $vs->arraySize2($fo->f_alta_responsa1);
		$f_toma_1 = $vs->arraySize2($fo->f_toma_decisiones1);
		$f_sed_d1 = $vs->arraySize2($fo->f_sed_deficiente1);
		$f_confl1 = $vs->arraySize2($fo->f_conflicto_rol1);
		$f_alta_1 = $vs->arraySize2($fo->f_alta_claridad_funcio1);
		$f_inco_1 = $vs->arraySize2($fo->f_inco_distrib_trabajo1);
		$f_turno1 = $vs->arraySize2($fo->f_turnos_rotativos1);
		$f_relac1 = $vs->arraySize2($fo->f_relacio_interp1);
		$f_inest1 = $vs->arraySize2($fo->f_inesta_laboral1);
		$f_monot2 = $vs->arraySize2($fo->f_monot_trabajo2);
		$f_sobre2 = $vs->arraySize2($fo->f_sobrec_laboral2);
		$f_minuc2 = $vs->arraySize2($fo->f_minuci_tarea2);
		$f_alta_2 = $vs->arraySize2($fo->f_alta_responsa2);
		$f_toma_2 = $vs->arraySize2($fo->f_toma_decisiones2);
		$f_sed_d2 = $vs->arraySize2($fo->f_sed_deficiente2);
		$f_confl2 = $vs->arraySize2($fo->f_conflicto_rol2);
		$f_alta_2 = $vs->arraySize2($fo->f_alta_claridad_funcio2);
		$f_inco_2 = $vs->arraySize2($fo->f_inco_distrib_trabajo2);
		$f_turno2 = $vs->arraySize2($fo->f_turnos_rotativos2);
		$f_relac2 = $vs->arraySize2($fo->f_relacio_interp2);
		$f_inest2 = $vs->arraySize2($fo->f_inesta_laboral1);
		$ipiel_as = $vs->arraySize2($fo->i_piel_anexos);
		$iorg_ses = $vs->arraySize2($fo->i_org_sentidos);
		$irespiro = $vs->arraySize2($fo->i_respiratorio);
		$icardior = $vs->arraySize2($fo->i_cardio_vascular);
		$idigesta = $vs->arraySize2($fo->i_digestivo);
		$igenitoo = $vs->arraySize2($fo->i_genito_urinario);
		$imusculo = $vs->arraySize2($fo->i_musculo_esqueletico);
		$iendocra = $vs->arraySize2($fo->i_endocrino);
		$ihemolio = $vs->arraySize2($fo->i_hemolinfatico);
		$inervioa = $vs->arraySize2($fo->i_nervioso);
		$k_cicatrices = $vs->arraySize2($fo->k_cicatrices);
		$k_tatuajes = $vs->arraySize2($fo->k_tatuajes);
		$k_piel_faneras = $vs->arraySize2($fo->k_piel_faneras);
		$k_parpados = $vs->arraySize2($fo->k_parpados);
		$k_conjuntivas = $vs->arraySize2($fo->k_conjuntivas);
		$k_pupilas = $vs->arraySize2($fo->k_pupilas);
		$k_cornea = $vs->arraySize2($fo->k_cornea);
		$k_motilidad = $vs->arraySize2($fo->k_motilidad);
		$k_auditivo_externo = $vs->arraySize2($fo->k_auditivo_externo);
		$k_pabellon = $vs->arraySize2($fo->k_pabellon);
		$k_timpanos = $vs->arraySize2($fo->k_timpanos);
		$k_labios = $vs->arraySize2($fo->k_labios);
		$k_lengua = $vs->arraySize2($fo->k_lengua);
		$k_faringe = $vs->arraySize2($fo->k_faringe);
		$k_amigdalas = $vs->arraySize2($fo->k_amigdalas);
		$k_dentadura = $vs->arraySize2($fo->k_dentadura);
		$k_tabique = $vs->arraySize2($fo->k_tabique);
		$k_cornetes = $vs->arraySize2($fo->k_cornetes);
		$k_mucosas = $vs->arraySize2($fo->k_mucosas);
		$k_senos_paranasales = $vs->arraySize2($fo->k_senos_paranasales);
		$k_tiroides = $vs->arraySize2($fo->k_tiroides);
		$k_movilidad = $vs->arraySize2($fo->k_movilidad);
		$k_mamas = $vs->arraySize2($fo->k_mamas);
		$k_corazon = $vs->arraySize2($fo->k_corazon);
		$k_pulmones = $vs->arraySize2($fo->k_pulmones);
		$k_parrilla_costal = $vs->arraySize2($fo->k_parrilla_costal);
		$k_visceras = $vs->arraySize2($fo->k_visceras);
		$k_parde_abdominal = $vs->arraySize2($fo->k_parde_abdominal);
		$k_flexibilidad = $vs->arraySize2($fo->k_flexibilidad);
		$k_desviacion = $vs->arraySize2($fo->k_desviacion);
		$k_dolor = $vs->arraySize2($fo->k_dolor);
		$k_pelvis = $vs->arraySize2($fo->k_pelvis);
		$k_genitales = $vs->arraySize2($fo->k_genitales);
		$k_vascular = $vs->arraySize2($fo->k_vascular);
		$k_mie_superiores = $vs->arraySize2($fo->k_mie_superiores);
		$k_mie_inferiores = $vs->arraySize2($fo->k_mie_inferiores);
		$k_fuerza = $vs->arraySize2($fo->k_fuerza);
		$k_sensibilidad = $vs->arraySize2($fo->k_sensibilidad);
		$k_marcha = $vs->arraySize2($fo->k_marcha);
		$k_reflejos = $vs->arraySize2($fo->k_reflejos);
		$m_pre = $vs->arraySize2($fo->m_pre);
		$m_def = $vs->arraySize2($fo->m_def);
		$m_pre2 = $vs->arraySize2($fo->m_pre2);
		$m_def2 = $vs->arraySize2($fo->m_def2);
		$m_pre3 = $vs->arraySize2($fo->m_pre3);
		$m_def3 = $vs->arraySize2($fo->m_def3);

		$nAptoTrabajo = $vs->nAptitudMedica($fo->n_apto);







		//echo(json_encode($bevaluacion));




		// Setup a filename
		$documentFileName = "fun.pdf";

		// Create the mPDF document
		$document = new PDF([
			'mode' => 'utf-8',
			'format' => 'A4',
			'margin_header' => '3',
			'margin_top' => '11',
			'margin_bottom' => '11',
			'margin_footer' => '2',
			'margin_left' => '6',
			'margin_right' => '6',


		]);

		// Set some header informations for output
		$header = [
			'Content-Type' => 'application/pdf',
			'Content-Disposition' => 'inline; filename="' . $documentFileName . '"'
		];




		$document->WriteHTML('
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>ReporteFAM</title>
		<style type="text/css">
		.Titulo {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 11px;
			font-weight: bold;
			text-align: center;
		}
		.headerMorado {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 11px;
			font-weight: bold;
			background-color: #d9d9ff;
			border: thin solid #000000;
		}
		.HeaderVerde {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 11px;
			font-weight: bold;
			background-color: #ccffcc;
			text-align: left;
		}
		.HeaderCeleste {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 9px;
			font-weight: bold;
			background-color: #ccffff;
			text-align: left;
		}
		.seccionBlanca {
			border: 1px solid #000000;
			font-family: Arial, Helvetica, sans-serif;
			font-size: 10px;
			font-weight: normal;
		}
		.ImagenHeader {
			text-align: center;
		}
		.bordesTabla {
			border: 1px solid #000000;
		}
		.headerVerdeCentrado {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 11px;
			font-weight: bold;
			background-color: #ccffcc;
			text-align: center;
		}
		.textInvertidoverde {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 5pt;
			background-color: #CCFFCC;
		}
		.textInvertidoCeleste {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 5pt;
			background-color: #CCFFFF;
		}
		.titulo {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 14px;
			font-weight: bold;
			color: #0033CC;
		}
		.AlmEsp1 {
			font-size: xx-large;
		}
		.AlmEsp2 {
			color: #FF0000;
			font-size: 28px;
			font-weight: bold;
		}
		.AlmEsp3 {
			color: #006600;
			font-size: 28px;
			font-weight: bold;
		}
		</style>
		<meta name="description" content="REPORTE FORMULARIO APTITUD MEDICA" />
		</head>

		<body>
		<div>
		<div class="ImagenHeader" style="width: 1650px">


		<table style="width: 100%">
	<tr>
		<td><span class="AlmEsp3">ALMACENES</span> <span class="AlmEsp2">
		<strong>ESPAÑA</strong></span></td>
		<td class="titulo">HISTORIA CLINICA PRE-OCUPACIONAL</td>
	</tr>
</table>



		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="18" class="headerMorado">A. DATOS DEL ESTABLECIMIENTO - EMPRESA Y USUARIO</td>
			</tr>
			<tr>
				<td colspan="3" class="HeaderVerde">INSTITUCIÓN O EMPRESA</td>
				<td colspan="3" class="HeaderVerde">RUC</td>
				<td colspan="3" class="HeaderVerde">CIUDAD</td>
				<td colspan="3" class="HeaderVerde">ESTA. SALUD</td>
				<td colspan="3" class="HeaderVerde">N. H. CLINICA</td>
				<td colspan="3" class="HeaderVerde">N. ARCHIVO</td>
			</tr>
			<tr>
				<td colspan="3" class="seccionBlanca">' . $empresa . '</td>
				<td colspan="3" class="seccionBlanca">' . $ruc . '</td>
				<td colspan="3" class="seccionBlanca">' . $ciudad . '</td>
				<td colspan="3" class="seccionBlanca">' . $estableSalud . '</td>
				<td colspan="3" class="seccionBlanca">' . $p->pac_id . '</td>
				<td colspan="3" class="seccionBlanca">' . $fo->fo_id . '</td>
			</tr>
			<tr>
				<td rowspan="2" style="width: 32px" class="HeaderVerde">PRIMER APELLIDO</td>
				<td rowspan="2" class="HeaderVerde" style="width: 29px">SEGUNDO APELLIDO</td>
				<td rowspan="2" class="HeaderVerde">PRIMER NOMBRE</td>
				<td rowspan="2" style="width: 10px" class="HeaderVerde">SEGUNDO NOMBRE</td>
				<td rowspan="2" class="HeaderVerde">SEXO</td>
				<td rowspan="2" class="HeaderVerde">EDAD</td>
				<td colspan="5" style="height: 23px" class="headerVerdeCentrado">RELIGION</td>
				<td colspan="4" rowspan="2" class="HeaderVerde">&nbsp;GRUPO SANGUÍNEO</td>
				<td colspan="3" rowspan="2" class="HeaderVerde">LATERALIDAD</td>
			</tr>
			<tr>
				<td class="textInvertidoverde" style="width: 31px">CATOLICA</td>
				<td class="textInvertidoverde" style="width: 41px">EVANGELICA</td>
				<td class="textInvertidoverde" style="width: 41px">TESTIGO DE JEHOVA</td>
				<td class="textInvertidoverde" style="width: 53px">MORMONA</td>
				<td class="textInvertidoverde">OTRAS</td>
			</tr>
			<tr class="seccionBlanca">
				<td style="width: 32px" class="seccionBlanca">' . $p->pac_primer_apellido . '</td>
				<td style="width: 29px" class="seccionBlanca">' . $p->pac_segundo_apellido . '</td>
				<td class="seccionBlanca">' . $p->pac_primero_nombre . '</td>
				<td style="width: 10px" class="seccionBlanca">' . $p->pac_segundo_nombre . '</td>
				<td class="seccionBlanca">' . $sexo . '</td>
				<td class="seccionBlanca">' . $edad . '</td>
				<td style="width: 31px" class="seccionBlanca">' . $religion[0] . '</td>
				<td style="width: 41px" class="seccionBlanca">' . $religion[1] . '</td>
				<td style="width: 41px" class="seccionBlanca">' . $religion[2] . '</td>
				<td style="width: 53px" class="seccionBlanca">' . $religion[3] . '</td>
				<td class="seccionBlanca">' . $religion[4] . '</td>
				<td colspan="4" class="seccionBlanca">' . $sangre . '</td>
				<td colspan="3" class="seccionBlanca">' . $lateralidad . '</td>
			</tr>
			<tr>
				<td colspan="5" class="headerVerdeCentrado">ORIENTACIÓN SEXUAL</td>
				<td colspan="5" class="headerVerdeCentrado">IDENTIDAD DE GÉNERO</td>
				<td colspan="4" class="HeaderVerde">DISCAPACIDAD</td>
				<td rowspan="2" style="width: 4px" class="HeaderVerde">FECHA DE INGRESO
				AL TRABAJO</td>
				<td rowspan="2" class="HeaderVerde">PUESTO DE TRABAJO</td>
				<td rowspan="2" class="HeaderVerde">ÁREA DE TRABAJO</td>
				<td rowspan="2" class="HeaderVerde">ACTIVID<br />
				ADES<br />
				PUESTO<br />
				TRABAJO</td>
			</tr>
			<tr>
				<td style="width: 32px" class="textInvertidoverde">LESBIANA</td>
				<td class="textInvertidoverde" style="width: 25px">GAY</td>
				<td class="textInvertidoverde" style="width: 25px">BISEXUAL</td>
				<td style="width: 25px" class="textInvertidoverde">HETEROS<br />
				EXUAL</td>
				<td class="textInvertidoverde" style="width: 25px">NO SABE / NO RESPONDE</td>
				<td class="textInvertidoverde">FEMENINO</td>
				<td style="width: 31px" class="textInvertidoverde">MASCULINO</td>
				<td class="textInvertidoverde" style="width: 41px">TRANS-FEMENINO</td>
				<td class="textInvertidoverde" style="width: 41px">TRANS-MASCULINO</td>
				<td class="textInvertidoverde" style="width: 53px">NO SABE NO/ RESPONDE</td>
				<td class="textInvertidoverde">SI</td>
				<td class="textInvertidoverde">NO</td>
				<td class="textInvertidoverde">TIPO</td>
				<td class="textInvertidoverde">%</td>
			</tr>
			<tr class="seccionBlanca">
				<td  class="seccionBlanca">' . $orientacionSexual[0] . '</td>
				<td  class="seccionBlanca">' . $orientacionSexual[1] . '</td>
				<td class="seccionBlanca">' . $orientacionSexual[2] . '</td>
				<td  class="seccionBlanca">' . $orientacionSexual[3] . '</td>
				<td class="seccionBlanca">' . $orientacionSexual[4] . '</td>
				<td class="seccionBlanca">' . $identidadGenero[0] . '</td>
				<td  class="seccionBlanca">' . $identidadGenero[1] . '</td>
				<td  class="seccionBlanca">' . $identidadGenero[2] . '</td>
				<td  class="seccionBlanca">' . $identidadGenero[3] . '</td>
				<td  class="seccionBlanca">' . $identidadGenero[4] . '</td>
				<td class="seccionBlanca">' . $discapacidad[0] . '</td>
				<td class="seccionBlanca">' . $discapacidad[1] . '</td>
				<td class="seccionBlanca">' . $tipoDiscapacidad . '</td>
				<td class="seccionBlanca">' . $fo->a_porcentaje_discapacidad . '</td>
				<td style="width: 4px" class="seccionBlanca">' . $fo->a_fecha_ingre_trabajo . '</td>
				<td class="seccionBlanca">' . $cargo . '</td>
				<td class="seccionBlanca">' . $departamentos . '</td>
				<td class="seccionBlanca">' . $fo->a_actividad_puesto_trabajo . '</td>
			</tr>
			</table>
			<table style="width: 100%">
			<tr>
			<td class="headerMorado">B. MOTIVO DE CONSULTA</td>
			</tr>
			<tr>
			<td style="height: 23px" class="seccionBlanca">' . $fo->b_descripcion_consulta . '</td>
			</tr>
			</table>

		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="14" class="headerMorado">C. ANTECEDENTES PERSONALES</td>
			</tr>
			<tr>
				<td colspan="14" class="HeaderVerde">ANTECEDENTES CLÍNICOS Y QUIRÚRGICOS</td>
			</tr>
			<tr>
				<td colspan="14" class="seccionBlanca">' . $fo->c_anteceden_clinicos_quirur . '</td>
			</tr>
			<tr>
				<td colspan="14" class="HeaderVerde">ANTECEDENTES GINICO OBSTÉTRICOS</td>
			</tr>
			<tr class="HeaderCeleste">
				<td rowspan="2" class="HeaderCeleste">MENARQUÍA</td>
				<td rowspan="2" class="HeaderCeleste">CICLOS</td>
				<td rowspan="2" class="HeaderCeleste">FECHA ULTIMA MENSTRUACION</td>
				<td rowspan="2" class="HeaderCeleste">GESTAS</td>
				<td rowspan="2" class="HeaderCeleste" style="width: 46px">PARTOS</td>
				<td rowspan="2" class="HeaderCeleste">CESÁREAS</td>
				<td rowspan="2" class="HeaderCeleste">ABORTOS</td>
				<td style="height: 26px" colspan="2" class="HeaderCeleste">HIJOS</td>
				<td style="height: 26px" colspan="2" class="HeaderCeleste">VIDA SEXUAL ACTIVA</td>
				<td style="height: 26px" colspan="3" class="HeaderCeleste">METO. PLANI. FAMILIAR</td>
			</tr>
			<tr class="textInvertidoCeleste">
				<td class="textInvertidoCeleste">VIVOS</td>
				<td class="textInvertidoCeleste">MUERTOS</td>
				<td class="textInvertidoCeleste">SI</td>
				<td class="textInvertidoCeleste">NO</td>
				<td class="textInvertidoCeleste">SI</td>
				<td class="textInvertidoCeleste">NO</td>
				<td class="textInvertidoCeleste">TIPO</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca">' . $fo->c_menarquia . '</td>
				<td class="seccionBlanca">' . $fo->c_ciclos_menarquia . '</td>
				<td class="seccionBlanca">' . $fo->c_fecha_ultima_menstrua . '</td>
				<td class="seccionBlanca">' . $fo->c_gestas . '</td>
				<td class="seccionBlanca">' . $fo->c_partos . '</td>
				<td class="seccionBlanca">' . $fo->c_cesareas . '</td>
				<td class="seccionBlanca">' . $fo->c_abortos . '</td>
				<td class="seccionBlanca">' . $fo->c_hijos_vivos . '</td>
				<td class="seccionBlanca">' . $fo->c_hijos_muertos . '</td>
				<td class="seccionBlanca">' . $vidaSexuActiva[0] . '</td>
				<td class="seccionBlanca">' . $vidaSexuActiva[1] . '</td>
				<td class="seccionBlanca">' . $metodoPlani[0] . '</td>
				<td class="seccionBlanca">' . $metodoPlani[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tipo_metodo_pla_familiar . '</td>
			</tr>
			<tr class="HeaderCeleste">
				<td class="HeaderCeleste">EXÁMENES<br />
				REALIZADOS</td>
				<td class="HeaderCeleste">SI</td>
				<td class="HeaderCeleste">NO</td>
				<td class="HeaderCeleste">TIEMPO<br />
				(años)</td>
				<td style="width: 46px" class="HeaderCeleste">RESULTADO</td>
				<td class="HeaderCeleste">EXÁMENES REALIZADOS</td>
				<td class="HeaderCeleste">SI</td>
				<td class="HeaderCeleste">NO</td>
				<td class="HeaderCeleste">TIEMPO<br />
				(años)</td>
				<td colspan="5" class="HeaderCeleste">RESULTADO</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca">PAPANICOLAOU</td>
				<td class="seccionBlanca">' . $exPapani[0] . '</td>
				<td class="seccionBlanca">' . $exPapani[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_exa_papanicolao . '</td>
				<td class="seccionBlanca">' . $fo->c_result_exa_papanicolao . '</td>
				<td class="seccionBlanca">ECO MAMARIO</td>
				<td class="seccionBlanca">' . $exEcoma[0] . '</td>
				<td class="seccionBlanca">' . $exEcoma[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_exa_ecomamario . '</td>
				<td colspan="5" class="seccionBlanca">' . $fo->c_result_exa_ecomamario . '</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca">COLPOSCOPIA</td>
				<td class="seccionBlanca">' . $exColo[0] . '</td>
				<td class="seccionBlanca">' . $exColo[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_exa_colposcopia . '</td>
				<td class="seccionBlanca">' . $fo->c_result_exa_colposcopia . '</td>
				<td class="seccionBlanca">MAMOGRAFIA</td>
				<td class="seccionBlanca">' . $exMamog[0] . '</td>
				<td class="seccionBlanca">' . $exMamog[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_exa_mamografia . '</td>
				<td colspan="5" class="seccionBlanca">' . $fo->c_result_exa_mamografia . '</td>
			</tr>
			<tr>
				<td colspan="14" class="HeaderVerde">ANTECEDENTES REPRODUCTIVOS MASCULINOS</td>
			</tr>
			<tr>
				<td rowspan="2" class="HeaderCeleste">EXÁMENES REALIZADOS</td>
				<td rowspan="2" class="HeaderCeleste">SI</td>
				<td rowspan="2" class="HeaderCeleste">NO</td>
				<td rowspan="2" class="HeaderCeleste">TIEMPO(años)</td>
				<td colspan="3" rowspan="2" class="HeaderCeleste">RESULTADO</td>
				<td colspan="5" class="HeaderCeleste">MÉTO. PLANI. FAMILIAR</td>
				<td colspan="2" class="HeaderCeleste">HIJOS</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste">SI</td>
				<td class="textInvertidoCeleste">NO</td>
				<td colspan="3" class="textInvertidoCeleste">TIPO</td>
				<td class="textInvertidoCeleste">SI</td>
				<td class="textInvertidoCeleste">NO</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca">ANTIGENO PROSTATICO</td>
				<td class="seccionBlanca">' . $exAntPro[0] . '</td>
				<td class="seccionBlanca">' . $exAntPro[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_exa_antigeno_prostatico . '</td>
				<td class="seccionBlanca" colspan="3">' . $fo->c_result_exa_antigeno_prostatico . '</td>
				<td class="seccionBlanca" rowspan="2">' . $metodoPlani[0] . '</td>
				<td class="seccionBlanca" rowspan="2">' . $metodoPlani[1] . '</td>
				<td class="seccionBlanca" rowspan="2" colspan="3">' . $fo->c_tipo_metodo_pla_familiar . '</td>
				<td class="seccionBlanca" rowspan="2">' . $fo->c_hijos_vivos . '</td>
				<td class="seccionBlanca" rowspan="2">' . $fo->c_hijos_muertos . '</td>

			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca">ECO PROSTATICO</td>
				<td class="seccionBlanca">' . $exEcoPro[0] . '</td>
				<td class="seccionBlanca">' . $exEcoPro[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_exa_ecoprostatico . '</td>
				<td class="seccionBlanca" colspan="3">' . $fo->c_result_exa_ecoprostatico . '</td>
			</tr>
			<tr>
				<td colspan="7" class="HeaderVerde">HÁBITOS TÓXICOS </td>
				<td colspan="7" class="HeaderVerde">ESTILO DE VIDA</td>
			</tr>
			<tr>
				<td style="height: 23px" class="HeaderCeleste">CONSUMOS NOCIVOS&nbsp;</td>
				<td style="height: 23px" class="HeaderCeleste">SI</td>
				<td style="height: 23px" class="HeaderCeleste">NO</td>
				<td style="height: 23px" class="HeaderCeleste">TIEMPO DE CONSUMO<br />
				(meses)</td>
				<td style="height: 23px; width: 46px" class="HeaderCeleste">CANTIDAD</td>
				<td style="height: 23px" class="HeaderCeleste">EX CONSUMIDOR</td>
				<td style="height: 23px" class="HeaderCeleste">TIEMPO DE <br />
				ABSTINENCIA<br />
				(meses)</td>
				<td style="height: 23px" class="HeaderCeleste">ESTILO</td>
				<td style="height: 23px" class="HeaderCeleste">SI</td>
				<td style="height: 23px" class="HeaderCeleste">NO</td>
				<td style="height: 23px" class="HeaderCeleste">&nbsp;¿CUÁL?</td>
				<td style="height: 23px" colspan="3" class="HeaderCeleste">TIEMPO /
				CANTIDAD</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca">TABACO</td>
				<td class="seccionBlanca">' . $conTabac[0] . '</td>
				<td class="seccionBlanca">' . $conTabac[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_cons_tabaco . '</td>
				<td class="seccionBlanca">' . $fo->c_cantidad_cons_tabaco . '</td>
				<td class="seccionBlanca">' . $exConsuTaba[0] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiem_absti_tabaco . '</td>
				<td class="seccionBlanca">ACTIVIDAD FISICA</td>
				<td class="seccionBlanca">' . $actiFisica[0] . '</td>
				<td class="seccionBlanca">' . $actiFisica[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_desc_actifisica . '</td>
				<td colspan="3" class="seccionBlanca">' . $fo->c_tiemp_actifisica . '</td>
			</tr>
			<tr>
				<td class="seccionBlanca">ALCOHOL</td>
				<td class="seccionBlanca">' . $conAlcho[0] . '</td>
				<td class="seccionBlanca">' . $conAlcho[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiempo_cons_alcohol . '</td>
				<td class="seccionBlanca">' . $fo->c_cantidad_cons_alcohol . '</td>
				<td class="seccionBlanca">' . $conAlcho[0] . '</td>
				<td class="seccionBlanca">' . $fo->c_tiem_abst_alcohol . '</td>
				<td class="seccionBlanca" rowspan="3">MEDICACION HABITUAL</td>
				<td class="seccionBlanca" rowspan="3">' . $mediHabitu[0] . '</td>
				<td class="seccionBlanca" rowspan="3">' . $mediHabitu[1] . '</td>
				<td class="seccionBlanca">' . $fo->c_medicacion_habitual1 . '</td>
				<td colspan="3" class="seccionBlanca">' . $fo->c_tiem_medicacion_habitual1 . '</td>
			</tr>
			<tr>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_cons_otra_droga . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $conOtrDro1[0] . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $conOtrDro1[1] . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_tiempo_cons_otras . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_cantidad_cons_otras . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $exConOtra1[0] . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_tiem_abst_otras . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_medicacion_habitual2 . '</td>
				<td style="height: 23px" colspan="3" class="seccionBlanca">' . $fo->c_tiem_medicacion_habitual2 . '</td>
			</tr>
			<tr>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_cons_otra_droga2 . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $conOtrDro2[0] . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $conOtrDro2[1] . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_tiempo_cons_otras2 . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_cantidad_cons_otras2 . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $exConOtra2[0] . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_tiem_abst_otras2 . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->c_medicacion_habitual3 . '</td>
				<td style="height: 23px" colspan="3" class="seccionBlanca">' . $fo->c_tiem_medicacion_habitual3 . '</td>
			</tr>
			</table>

		</div>

		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="11" class="headerMorado">D. ANTECEDENTES DE TRABAJO</td>
			</tr>
			<tr>
				<td colspan="11" class="HeaderVerde">ANTECEDENTES DE EMPLEOS ANTERIORES</td>
			</tr>
			<tr>
				<td rowspan="2" class="HeaderVerde">EMPRESA</td>
				<td rowspan="2" class="HeaderVerde" style="width: 230px">PUESTO DE TRABAJO</td>
				<td rowspan="2" class="HeaderVerde">ACTIVIDADES QUE DESEMPEÑAN</td>
				<td rowspan="2" class="HeaderVerde">TIEMPO DE<br />
				TRABAJO</td>
				<td colspan="6" style="height: 36px" class="HeaderVerde">RIESGO</td>
				<td rowspan="2" class="HeaderVerde">OBSERVACIONES</td>
			</tr>
			<tr class="textInvertidoverde">
				<td class="textInvertidoverde" style="height: 11px">FÍSICO</td>
				<td style="height: 11px" class="textInvertidoverde">MECÁNICO</td>
				<td style="height: 11px" class="textInvertidoverde">QUÍMICO</td>
				<td style="height: 11px" class="textInvertidoverde">BIOLÓGICO</td>
				<td style="height: 11px" class="textInvertidoverde">ERGONÓMICO</td>
				<td style="height: 11px" class="textInvertidoverde">PSICOSOCIAL</td>
			</tr>
			<tr>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_empresa . '</td>
				<td class="seccionBlanca" >' . $fo->d_puesto_trabajo . '</td>
				<td class="seccionBlanca">' . $fo->d_actividad . '</td>
				<td class="seccionBlanca">' . $fo->d_tiempo . '</td>
				<td class="seccionBlanca">' . $riesgofisic[0] . '</td>
				<td class="seccionBlanca">' . $riesgomecan[0] . '</td>
				<td class="seccionBlanca">' . $riesgoquimi[0] . '</td>
				<td class="seccionBlanca">' . $riesgobiolo[0] . '</td>
				<td class="seccionBlanca">' . $riesgoergon[0] . '</td>
				<td class="seccionBlanca">' . $riesgopsico[0] . '</td>
				<td class="seccionBlanca">' . $fo->d_observaciones . '</td>
			</tr>
			<tr>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_empresa2 . '</td>
				<td class="seccionBlanca" >' . $fo->d_puesto_trabajo2 . '</td>
				<td class="seccionBlanca" >' . $fo->d_actividad2 . '</td>
				<td class="seccionBlanca" >' . $fo->d_tiempo2 . '</td>
				<td class="seccionBlanca" >' . $riesgofisic2[0] . '</td>
				<td class="seccionBlanca" >' . $riesgomecan2[0] . '</td>
				<td class="seccionBlanca" >' . $riesgoquimi2[0] . '</td>
				<td class="seccionBlanca" >' . $riesgobiolo2[0] . '</td>
				<td class="seccionBlanca" >' . $riesgoergon2[0] . '</td>
				<td class="seccionBlanca" >' . $riesgopsico2[0] . '</td>
				<td class="seccionBlanca" >' . $fo->d_observaciones2 . '</td>
			</tr>
			<tr>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_empresa3 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_puesto_trabajo3 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_actividad3 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_tiempo3 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgofisic3[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgomecan3[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgoquimi3[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgobiolo3[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgoergon3[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgopsico3[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_observaciones3 . '</td>
			</tr>
			<tr>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_empresa4 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_puesto_trabajo4 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_actividad4 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_tiempo4 . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgofisic4[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgomecan4[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgoquimi4[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgobiolo4[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgoergon4[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $riesgopsico4[0] . '</td>
				<td class="seccionBlanca" style="height: 20px">' . $fo->d_observaciones4 . '</td>
			</tr>
			<tr>
				<td colspan="11" class="HeaderVerde">ACCIDENTES DE TRABAJO (DESCRIPCIÓN)</td>
			</tr>
			<tr>
				<td class="seccionBlanca" colspan="3">&nbsp;FUE CALIFICADA POR EL INSTITUTO DE SEGURIDAD SOCIAL CORRESPONDIENTE: </td>
				<td >&nbsp;</td>
				<td class="seccionBlanca">SI</td>
				<td class="seccionBlanca">' . $califiSriAcc[0] . '</td>
				<td>&nbsp;</td>
				<td class="seccionBlanca">NO</td>
				<td class="seccionBlanca">' . $califiSriAcc[1] . '</td>
				<td class="seccionBlanca">FECHA:</td>
				<td class="seccionBlanca">' . $fo->d_fecha_acci . '</td>
			</tr>
			<tr>
				<td class="seccionBlanca" colspan="3">ESPECIFICAR: ' . $fo->d_especificar_acci . '</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td colspan="11" class="seccionBlanca" style="height: 18px">DESCRIPCION: ' . $fo->d_acci_trabajo_dec . '</td>
			</tr>
			<tr>
				<td colspan="11" class="HeaderVerde">ENFERMEDADES PROFESIONALES </td>
			</tr>
			<tr>
				<td class="seccionBlanca" colspan="3">&nbsp;FUE CALIFICADA POR EL INSTITUTO DE
				SEGURIDAD SOCIAL CORRESPONDIENTE: </td>
				<td>&nbsp;</td>
				<td class="seccionBlanca">SI</td>
				<td class="seccionBlanca">' . $califiSriEp[0] . '</td>
				<td>&nbsp;</td>
				<td class="seccionBlanca">NO</td>
				<td class="seccionBlanca">' . $califiSriEp[1] . '</td>
				<td class="seccionBlanca">FECHA:</td>
				<td class="seccionBlanca">' . $fo->d_fecha_ep . '</td>
			</tr>
			<tr>
				<td class="seccionBlanca" colspan="3" style="height: 23px">ESPECIFICAR: ' . $fo->d_especificar_ep . '</td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
				<td style="height: 23px"></td>
			</tr>
			<tr>
				<td class="seccionBlanca" colspan="11">DESCRIPCION:' . $fo->d_enfe_profesi_dec . '</td>
			</tr>
		</table>
				<table style="width: 100%" class="bordesTabla">
					<tr>
						<td class="headerMorado" colspan="16">E. ANTECEDENTES FAMILIARES
						(DETALLAR EL PARENTESCO)</td>
					</tr>
					<tr>
						<td class="textInvertidoverde">1. ENFERMEDAD CARDIO-VASCULAR</td>
						<td class="seccionBlanca">' . $edecariovas[0] . '</td>
						<td class="textInvertidoverde">2. ENFERMEDAD METABÓLICA</td>
						<td class="seccionBlanca">' . $edemetbolic[0] . '</td>
						<td class="textInvertidoverde">3 ENFERMEDAD NEUROLÓGICA</td>
						<td class="seccionBlanca">' . $edeneuologi[0] . '</td>
						<td class="textInvertidoverde">4. ENFERMEDAD ONCOLÓGICA</td>
						<td class="seccionBlanca">' . $edeonclogic[0] . '</td>
						<td class="textInvertidoverde">5. ENFERMEDAD INFECCIOSA</td>
						<td class="seccionBlanca">' . $edeinfccios[0] . '</td>
						<td class="textInvertidoverde">6. ENFERMEDAD HEREDITARIA /
						CONGÉNITA</td>
						<td class="seccionBlanca">' . $edeherditar[0] . '</td>
						<td class="textInvertidoverde" style="width: 3px">7.
						DISCAPACIDADES</td>
						<td class="seccionBlanca">' . $ededisapaci[0] . '</td>
						<td class="textInvertidoverde">8. OTROS</td>
						<td class="seccionBlanca">' . $edecrisotra[0] . '</td>
					</tr>
					<tr>
						<td class="seccionBlanca" colspan="16" style="height: 23px">' . $fo->e_descripcion . '</td>
					</tr>
				</table>






				<table style="width: 100%" class="bordesTabla">
					<tr>
						<td class="headerMorado" colspan="28">F. FACTORES DE RIESGOS DEL
						PUESTO DE TRABAJO ACTUAL</td>
					</tr>
					<tr class="HeaderVerde">
						<td>#</td>
						<td colspan="10" class="HeaderVerde">PUESTO DE TRABAJO / ÁREA</td>
						<td colspan="10" class="HeaderVerde">ACTIVIDADES</td>
						<td colspan="7" class="HeaderVerde">MEDIDAS PREVENTIVAS</td>
					</tr>
					<tr>
						<td class="seccionBlanca">1</td>
						<td colspan="10" class="seccionBlanca">' . $fo->f_puestotrabajo1 . '</td>
						<td colspan="10" class="seccionBlanca">' . $fo->f_actividad1 . '</td>
						<td colspan="7" class="seccionBlanca">' . $fo->f_medidas_preventivas1 . '</td>
					</tr>
					<tr>
						<td class="seccionBlanca">2</td>
						<td colspan="10" class="seccionBlanca">' . $fo->f_puestotrabajo2 . '</td>
						<td colspan="10" class="seccionBlanca">' . $fo->f_actividad2 . '</td>
						<td colspan="7" class="seccionBlanca">' . $fo->f_medidas_preventivas2 . '</td>
					</tr>
					<tr>
						<td class="HeaderVerde">&nbsp;</td>
						<td class="HeaderVerde" colspan="12">FÍSICO</td>
						<td class="HeaderVerde" colspan="15">MECÁNICO</td>
					</tr>
					<tr>
						<td class="HeaderVerde" >#</td>
						<td class="textInvertidoverde" >Temperat<br />&nbsp;altas</td>
						<td class="textInvertidoverde" >Tempera<br />&nbsp;bajas</td>
						<td class="textInvertidoverde" >Radiación<br />&nbsp;Ionizante</td>
						<td class="textInvertidoverde" >Radiación Ionizante</td>
						<td class="textInvertidoverde" >Radiación <br />No Ionizante</td>
						<td class="textInvertidoverde" >Ruido</td>
						<td class="textInvertidoverde" >Vibración</td>
						<td class="textInvertidoverde" >Iluminación</td>
						<td class="textInvertidoverde" >Ventilación</td>
						<td class="textInvertidoverde" colspan="2" >Fluido<br />eléctrico</td>
						<td class="textInvertidoverde" >Otros:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </td>
						<td class="textInvertidoverde">Atrapami entre máquinas</td>
						<td class="textInvertidoverde">Atrapami entre superficies</td>
						<td class="textInvertidoverde">Atrapamiento entre objetos</td>
						<td class="textInvertidoverde">Caída de objetos</td>
						<td class="textInvertidoverde">Caídas al mismo nivel</td>
						<td class="textInvertidoverde">Caídas a diferente nivel</td>
						<td class="textInvertidoverde">Contacto eléctrico</td>
						<td class="textInvertidoverde">Contacto con superficies de trabajos</td>
						<td class="textInvertidoverde">Proyección de partículas fragmentos</td>
						<td class="textInvertidoverde">Proyección de fluidos</td>
						<td class="textInvertidoverde">Pinc<br /> hazos</td>
						<td class="textInvertidoverde">Cortes</td>
						<td class="textInvertidoverde">Atropella. por vehículos</td>
						<td class="textInvertidoverde">Choques /colisión vehicular</td>
						<td class="textInvertidoverde">Otros:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </td>
					</tr>
					<tr>
						<td style="width: 35px" class="seccionBlanca">1</td>
						<td style="width: 35px" class="seccionBlanca">' . $ftempeb1[0] . '</td>
						<td style="width: 29px" class="seccionBlanca">' . $ftempea1[0] . '</td>
						<td style="width: 27px" class="seccionBlanca">' . $fradiae1[0] . '</td>
						<td style="width: 4px" class="seccionBlanca">' . $fradiac1[0] . '</td>
						<td style="width: 32px" class="seccionBlanca">' . $fruidoa1[0] . '</td>
						<td style="width: 23px" class="seccionBlanca">' . $fvibrac1[0] . '</td>
						<td style="width: 8px" class="seccionBlanca">' . $filumin1[0] . '</td>
						<td style="width: 11px" class="seccionBlanca">' . $fventil1[0] . '</td>
						<td style="width: 24px" class="seccionBlanca">' . $ffluido1[0] . '</td>
						<td colspan="2" style="width: 19px" class="seccionBlanca">' . $ffisico1[0] . '</td>
						<td style="width: 225px" class="seccionBlanca">' . $fo->f_fisico_otro_desc1 . '</td>
						<td class="seccionBlanca">' . $fatrape1[0] . '</td>
						<td class="seccionBlanca">' . $fatrapa1[0] . '</td>
						<td class="seccionBlanca">' . $fatrapi1[0] . '</td>
						<td class="seccionBlanca">' . $fcaidaa1[0] . '</td>
						<td class="seccionBlanca">' . $faidasa1[0] . '</td>
						<td class="seccionBlanca">' . $fcaidas1[0] . '</td>
						<td class="seccionBlanca">' . $fcontac1[0] . '</td>
						<td class="seccionBlanca">' . $fcontab1[0] . '</td>
						<td class="seccionBlanca">' . $fproyea1[0] . '</td>
						<td class="seccionBlanca">' . $fproyeb1[0] . '</td>
						<td class="seccionBlanca">' . $fpincha1[0] . '</td>
						<td class="seccionBlanca">' . $fcortes1[0] . '</td>
						<td class="seccionBlanca">' . $ftropel1[0] . '</td>
						<td class="seccionBlanca">' . $fchoque1[0] . '</td>
						<td class="seccionBlanca">' . $fo->f_mecanico_otro_desc1 . '</td>
					</tr>
					<tr>
						<td style="width: 35px" class="seccionBlanca">2</td>
						<td style="width: 35px" class="seccionBlanca">' . $ftempeb2[0] . '</td>
						<td style="width: 29px" class="seccionBlanca">' . $ftempea2[0] . '</td>
						<td style="width: 27px" class="seccionBlanca">' . $fradiae2[0] . '</td>
						<td style="width: 4px" class="seccionBlanca">' . $fradiac2[0] . '</td>
						<td style="width: 32px" class="seccionBlanca">' . $fruidoa2[0] . '</td>
						<td style="width: 23px" class="seccionBlanca">' . $fvibrac2[0] . '</td>
						<td style="width: 8px" class="seccionBlanca">' . $filumin2[0] . '</td>
						<td style="width: 11px" class="seccionBlanca">' . $fventil2[0] . '</td>
						<td style="width: 24px" class="seccionBlanca">' . $ffluido2[0] . '</td>
						<td colspan="2" style="width: 19px" class="seccionBlanca">' . $ffisico2[0] . '</td>
						<td style="width: 225px" class="seccionBlanca">' . $fo->f_fisico_otro_desc2 . '</td>
						<td class="seccionBlanca">' . $fatrape2[0] . '</td>
						<td class="seccionBlanca">' . $fatrapa2[0] . '</td>
						<td class="seccionBlanca">' . $fatrapi2[0] . '</td>
						<td class="seccionBlanca">' . $fcaidaa2[0] . '</td>
						<td class="seccionBlanca">' . $faidasa2[0] . '</td>
						<td class="seccionBlanca">' . $fcaidas2[0] . '</td>
						<td class="seccionBlanca">' . $fcontac2[0] . '</td>
						<td class="seccionBlanca">' . $fcontab2[0] . '</td>
						<td class="seccionBlanca">' . $fproyea2[0] . '</td>
						<td class="seccionBlanca">' . $fproyeb2[0] . '</td>
						<td class="seccionBlanca">' . $fpincha2[0] . '</td>
						<td class="seccionBlanca">' . $fcortes2[0] . '</td>
						<td class="seccionBlanca">' . $ftropel2[0] . '</td>
						<td class="seccionBlanca">' . $fchoque2[0] . '</td>
						<td class="seccionBlanca">' . $fo->f_mecanico_otro_desc2 . '</td>
					</tr>
				</table>


				<table style="width: 100%" class="bordesTabla">
					<tr>
						<td class="HeaderVerde">&nbsp;</td>
						<td class="HeaderVerde" colspan="9">QUÍMICO</td>
						<td class="HeaderVerde" colspan="8">BIOLÓGICO</td>
						<td class="HeaderVerde" style="width: 4px">&nbsp;</td>
						<td class="HeaderVerde" colspan="4">&nbsp;ERGONÓMICO</td>
					</tr>
					<tr>
						<td class="HeaderVerde" style="width: 35px">#</td>
						<td class="textInvertidoverde" style="width: 35px">Solidos</td>
						<td class="textInvertidoverde" style="width: 29px">Polvos</td>
						<td class="textInvertidoverde" style="width: 27px">Humos</td>
						<td class="textInvertidoverde" style="width: 4px">Liquidos</td>
						<td class="textInvertidoverde" style="width: 32px">Vapores</td>
						<td class="textInvertidoverde" style="width: 23px">Aerosoles</td>
						<td class="textInvertidoverde" style="width: 8px">Neblinas</td>
						<td class="textInvertidoverde" style="width: 11px">Gaseosos</td>
						<td class="textInvertidoverde" style="width: 105px">Otros:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </td>
						<td class="textInvertidoverde">Virus</td>
						<td class="textInvertidoverde">Hongos</td>
						<td class="textInvertidoverde">Bacterias</td>
						<td class="textInvertidoverde">Parasitos</td>
						<td class="textInvertidoverde">Exposicion a vectores</td>
						<td class="textInvertidoverde">Exposicion a animales selvaticos</td>
						<td class="textInvertidoverde">Otros:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </td>
						<td class="textInvertidoverde">Manejo manual de cargas</td>
						<td class="textInvertidoverde">Movimiento repetitivos</td>
						<td class="textInvertidoverde">Posturas forzadas</td>
						<td class="textInvertidoverde">Trabajos con PVD</td>
						<td class="textInvertidoverde">Otros:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </td>
					</tr>
					<tr>
					<td style="width: 35px" class="seccionBlanca">1</td>
					<td style="width: 35px" class="seccionBlanca">' . $fsolid1[0] . '</td>
					<td style="width: 29px" class="seccionBlanca">' . $fpolvo1[0] . '</td>
					<td style="width: 27px" class="seccionBlanca">' . $fhumos1[0] . '</td>
					<td style="width: 4px" class="seccionBlanca">' . $fliqui1[0] . '</td>
					<td style="width: 32px" class="seccionBlanca">' . $fvapoo1[0] . '</td>
					<td style="width: 23px" class="seccionBlanca">' . $faeros1[0] . '</td>
					<td style="width: 8px" class="seccionBlanca">' . $fnebli1[0] . '</td>
					<td style="width: 11px" class="seccionBlanca">' . $fgaseo1[0] . '</td>
					<td style="width: 105px" class="seccionBlanca">' . $fo->f_quimico_otros_desc1 . '</td>
					<td class="seccionBlanca">' . $fvirus1[0] . '</td>
					<td class="seccionBlanca">' . $fhongo1[0] . '</td>
					<td class="seccionBlanca">' . $fbacte1[0] . '</td>
					<td class="seccionBlanca">' . $fparas1[0] . '</td>
					<td class="seccionBlanca">' . $fexpa_1[0] . '</td>
					<td class="seccionBlanca">' . $fexpb_1[0] . '</td>
					<td class="seccionBlanca">' . $fo->f_biologico_otro_desc1 . '</td>
					<td class="seccionBlanca">' . $fmanejo1[0] . '</td>
					<td class="seccionBlanca">' . $fmovimi1[0] . '</td>
					<td class="seccionBlanca">' . $fpostur1[0] . '</td>
					<td style="width: 5px" class="seccionBlanca">' . $ftrabaj1[0] . '</td>
					<td class="seccionBlanca">' . $fo->f_ergonomico_otro_desc1 . '</td>
					</tr>
					<tr>
					<td style="width: 35px" class="seccionBlanca">2</td>
					<td style="width: 35px" class="seccionBlanca">' . $fsolid2[0] . '</td>
					<td style="width: 29px" class="seccionBlanca">' . $fpolvo2[0] . '</td>
					<td style="width: 27px" class="seccionBlanca">' . $fhumos2[0] . '</td>
					<td style="width: 4px" class="seccionBlanca">' . $fliqui2[0] . '</td>
					<td style="width: 32px" class="seccionBlanca">' . $fvapoo2[0] . '</td>
					<td style="width: 23px" class="seccionBlanca">' . $faeros2[0] . '</td>
					<td style="width: 8px" class="seccionBlanca">' . $fnebli2[0] . '</td>
					<td style="width: 11px" class="seccionBlanca">' . $fgaseo2[0] . '</td>
					<td style="width: 105px" class="seccionBlanca">' . $fo->f_quimico_otros_desc2 . '</td>
					<td class="seccionBlanca">' . $fvirus2[0] . '</td>
					<td class="seccionBlanca">' . $fhongo2[0] . '</td>
					<td class="seccionBlanca">' . $fbacte2[0] . '</td>
					<td class="seccionBlanca">' . $fparas2[0] . '</td>
					<td class="seccionBlanca">' . $fexpa_2[0] . '</td>
					<td class="seccionBlanca">' . $fexpb_2[0] . '</td>
					<td class="seccionBlanca">' . $fo->f_biologico_otro_desc2 . '</td>
					<td class="seccionBlanca">' . $fmanejo2[0] . '</td>
					<td class="seccionBlanca">' . $fmovimi2[0] . '</td>
					<td class="seccionBlanca">' . $fpostur2[0] . '</td>
					<td style="width: 5px" class="seccionBlanca">' . $ftrabaj2[0] . '</td>
					<td class="seccionBlanca">' . $fo->f_ergonomico_otro_desc2 . '</td>
					</tr>

				</table>


				<table style="width: 100%" class="bordesTabla">
					<tr>
						<td class="HeaderVerde">&nbsp;</td>
						<td class="HeaderVerde" colspan="9">PSICOSOCIAL</td>
						<td class="HeaderVerde" style="width: 33px">&nbsp;</td>
						<td class="HeaderVerde" style="width: 48px">&nbsp;</td>
						<td class="HeaderVerde" style="width: 38px">&nbsp;</td>
						<td class="HeaderVerde">&nbsp;</td>
					</tr>
					<tr>
						<td class="HeaderVerde" style="width: 35px">#</td>
						<td class="textInvertidoverde" style="width: 35px">
						Monotonía del trabajo </td>
						<td class="textInvertidoverde" style="width: 29px">
						Sobrecarga laboral</td>
						<td class="textInvertidoverde" style="width: 27px">
						Minuciosidad de la tarea </td>
						<td class="textInvertidoverde" style="width: 45px">
						Alta responsabilidad</td>
						<td class="textInvertidoverde" style="width: 32px">
						Autonomía en la toma de decisiones</td>
						<td class="textInvertidoverde" style="width: 23px">
						Supervisión y estilos de dirección deficiente</td>
						<td class="textInvertidoverde" style="width: 8px">
						Conflicto de rol</td>
						<td class="textInvertidoverde" style="width: 11px">
						Falta de Claridad en las funciones</td>
						<td class="textInvertidoverde" style="width: 41px">Incorrecta
						<br />
						distribución <br />
						del trabajo </td>
						<td class="textInvertidoverde" style="width: 33px">Turnos<br />&nbsp;rotativos</td>
						<td class="textInvertidoverde" style="width: 48px">Relaciones<br />&nbsp;interpersonales </td>
						<td class="textInvertidoverde" style="width: 38px">inestabilidad
						laboral</td>
						<td class="textInvertidoverde" style="width: 105px">Otros:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </td>
					</tr>
					<tr>
						<td style="width: 35px" class="seccionBlanca">1</td>
						<td style="width: 35px" class="seccionBlanca">' . $f_monot1[0] . '</td>
						<td style="width: 29px" class="seccionBlanca">' . $f_sobre1[0] . '</td>
						<td style="width: 27px" class="seccionBlanca">' . $f_minuc1[0] . '</td>
						<td style="width: 45px" class="seccionBlanca">' . $f_alta_1[0] . '</td>
						<td style="width: 32px" class="seccionBlanca">' . $f_toma_1[0] . '</td>
						<td style="width: 23px" class="seccionBlanca">' . $f_sed_d1[0] . '</td>
						<td style="width: 8px" class="seccionBlanca">' . $f_confl1[0] . '</td>
						<td style="width: 11px" class="seccionBlanca">' . $f_alta_1[0] . '</td>
						<td style="width: 41px" class="seccionBlanca">' . $f_inco_1[0] . '</td>
						<td style="width: 33px" class="seccionBlanca">' . $f_turno1[0] . '</td>
						<td style="width: 48px" class="seccionBlanca">' . $f_relac1[0] . '</td>
						<td style="width: 38px" class="seccionBlanca">' . $f_inest1[0] . '</td>
						<td style="width: 105px" class="seccionBlanca">' . $fo->f_psicosocial_otro_desc1 . '</td>
					</tr>
					<tr>
						<td style="width: 35px" class="seccionBlanca">2</td>
						<td style="width: 35px" class="seccionBlanca">' . $f_monot2[0] . '</td>
						<td style="width: 29px" class="seccionBlanca">' . $f_sobre2[0] . '</td>
						<td style="width: 27px" class="seccionBlanca">' . $f_minuc2[0] . '</td>
						<td style="width: 45px" class="seccionBlanca">' . $f_alta_2[0] . '</td>
						<td style="width: 32px" class="seccionBlanca">' . $f_toma_2[0] . '</td>
						<td style="width: 23px" class="seccionBlanca">' . $f_sed_d2[0] . '</td>
						<td style="width: 8px" class="seccionBlanca">' . $f_confl2[0] . '</td>
						<td style="width: 11px" class="seccionBlanca">' . $f_alta_2[0] . '</td>
						<td style="width: 41x" class="seccionBlanca">' . $f_inco_2[0] . '</td>
						<td style="width: 33px" class="seccionBlanca">' . $f_turno2[0] . '</td>
						<td style="width: 48px" class="seccionBlanca">' . $f_relac2[0] . '</td>
						<td style="width: 38px" class="seccionBlanca">' . $f_inest2[0] . '</td>
						<td style="width: 105px" class="seccionBlanca">' . $fo->f_psicosocial_otro_desc2 . '</td>
					</tr>
				</table>


		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td class="headerMorado" >G. ACTIVIDADES EXTRA LABORALES </td>
			</tr>
			<tr>
				<td class="seccionBlanca" style="height: 20px">' . $fo->g_activ_extra_laborales . '</td>
			</tr>
		</table>
		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td class="headerMorado">H. ENFERMEDAD ACTUAL</td>
			</tr>
			<tr>
				<td class="seccionBlanca" style="height: 20px">' . $fo->h_enfermedad_actual . '</td>
			</tr>
		</table>
		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="10" class="headerMorado">I. REVISIÓN ACTUAL DE ÓRGANOS Y
				SISTEMAS</td>
			</tr>
			<tr>
				<td class="HeaderCeleste"  >1. PIEL - ANEXOS</td>
				<td style="height: 23px" class="seccionBlanca">' . $ipiel_as[0] . '</td>
				<td class="HeaderCeleste" style="height: 23px">3. RESPIRATORIO</td>
				<td style="height: 23px" class="seccionBlanca">' . $irespiro[0] . '</td>
				<td class="HeaderCeleste" style="height: 23px">5. DIGESTIVO</td>
				<td style="height: 23px" class="seccionBlanca">' . $idigesta[0] . '</td>
				<td style="height: 23px" class="HeaderCeleste">7. MÚSCULO ESQUELÉTICO</td>
				<td style="height: 23px" class="seccionBlanca">' . $imusculo[0] . '</td>
				<td style="height: 23px" class="HeaderCeleste">9. HEMO LINFÁTICO</td>
				<td style="height: 23px" class="seccionBlanca">' . $ihemolio[0] . '</td>
			</tr>
			<tr>
				<td class="HeaderCeleste">2. ÓRGANOS DE LOS SENTIDOS</td>
				<td class="seccionBlanca">' . $iorg_ses[0] . '</td>
				<td class="HeaderCeleste">4. CARDIO-VASCULAR</td>
				<td class="seccionBlanca">' . $icardior[0] . '</td>
				<td class="HeaderCeleste">6. GENITO - URINARIO</td>
				<td class="seccionBlanca">' . $igenitoo[0] . '</td>
				<td class="HeaderCeleste">8. ENDOCRINO</td>
				<td class="seccionBlanca">' . $iendocra[0] . '</td>
				<td class="HeaderCeleste">10. NERVIOSO</td>
				<td class="seccionBlanca">' . $inervioa[0] . '</td>
			</tr>
			<tr>
				<td colspan="10" class="seccionBlanca">' . $fo->i_descripcion . '</td>
			</tr>
		</table>
		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="9" class="headerMorado">J. CONSTANTES VITALES Y
				ANTROPOMETRÍA </td>
			</tr>
			<tr>
		<td class="textInvertidoverde" style="height: 32px">PRESIÓN ARTERIAL</td>
		<td class="textInvertidoverde" style="height: 32px">TEMPERATURA (°C)</td>
		<td class="textInvertidoverde" style="height: 32px">FRECUENCIA CARDIACA (Lat/min)</td>
		<td class="textInvertidoverde" style="height: 32px">SATURACIÓN DE OXÍGENO (O2%)</td>
		<td class="textInvertidoverde" style="height: 32px">FRECUENCIA RESPIRATORIA (fr/min)</td>
		<td class="textInvertidoverde" style="height: 32px">PESO (Kg)</td>
		<td class="textInvertidoverde" style="height: 32px">TALLA (cm)</td>
		<td class="textInvertidoverde" style="height: 32px">ÍNDICE DE MASA CORPORAL (kg/m2)</td>
		<td class="textInvertidoverde" style="height: 32px">PERÍMETRO ABDOMINAL (cm)</td>
	</tr>

			<tr>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_precion_arterial . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_temperatura . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_frecuencia_cardiaca . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_saturacion_oxigeno . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_frecuencia_respiratoria . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_peso . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_talla . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_indice_masa_corporal . '</td>
				<td style="height: 23px" class="seccionBlanca">' . $fo->j_perimetro_abdominal . '</td>
			</tr>
		</table>
		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td class="headerMorado">K. EXAMEN FÍSICO REGIONAL</td>
			</tr>
			<tr>
				<td class="HeaderVerde">REGIONES</td>
			</tr>
		</table>
		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td rowspan="3" class="textInvertidoCeleste" style="width: 79px">1. Piel</td>
				<td class="textInvertidoCeleste" style="width: 62px">a. Cicatrices</td>
				<td class="seccionBlanca">' . $k_cicatrices[0] . '</td>
				<td rowspan="3" class="textInvertidoCeleste" style="width: 61px">3. Oído</td>
				<td class="textInvertidoCeleste" style="width: 63px">a. C. auditivo externo</td>
				<td class="seccionBlanca">' . $k_auditivo_externo[0] . '</td>
				<td rowspan="4" style="width: 13px" class="textInvertidoCeleste">5. Nariz</td>
				<td class="textInvertidoCeleste" style="width: 43px">a. Tabique</td>
				<td class="seccionBlanca">' . $k_tabique[0] . '</td>
				<td rowspan="2" class="textInvertidoCeleste" style="width: 11px">8. Tórax</td>
				<td style="width: 8px" class="textInvertidoCeleste">a. Pulmones</td>
				<td class="seccionBlanca">' . $k_pulmones[0] . '</td>
				<td rowspan="2" class="textInvertidoCeleste" style="width: 52px">11. Pelvis</td>
				<td class="textInvertidoCeleste" style="width: 79px">a. Pelvis</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_pelvis[0] . '</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste" style="width: 62px">b. Tatuajes</td>
				<td class="seccionBlanca">' . $k_tatuajes[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 63px">b. Pabellón</td>
				<td class="seccionBlanca">' . $k_pabellon[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 43px">b. Cornetas</td>
				<td class="seccionBlanca">' . $k_cornetes[0] . '</td>
				<td style="width: 8px" class="textInvertidoCeleste">b. Parrilla Costal</td>
				<td class="seccionBlanca">' . $k_parrilla_costal[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 79px">b. Genitales</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_genitales[0] . '</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste" style="width: 62px">c. Piel y Faneras</td>
				<td class="seccionBlanca">' . $k_piel_faneras[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 63px">c. Tímpanos</td>
				<td class="seccionBlanca">' . $k_timpanos[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 43px">c. Mucosas</td>
				<td class="seccionBlanca">' . $k_mucosas[0] . '</td>
				<td rowspan="2" class="textInvertidoCeleste" style="width: 11px">9. Abdomen</td>
				<td style="width: 8px" class="textInvertidoCeleste">a. Vísceras</td>
				<td class="seccionBlanca">' . $k_visceras[0] . '</td>
				<td rowspan="3" class="textInvertidoCeleste" style="width: 52px">12. Extremidades</td>
				<td class="textInvertidoCeleste" style="width: 79px">a. Vascular</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_vascular[0] . '</td>
			</tr>
			<tr>
				<td rowspan="5" class="textInvertidoCeleste" style="width: 79px">2. Ojos</td>
				<td class="textInvertidoCeleste" style="width: 62px">a. Párpados</td>
				<td class="seccionBlanca">' . $k_parpados[0] . '</td>
				<td rowspan="5" class="textInvertidoCeleste" style="width: 61px">4. Oro faringe</td>
				<td class="textInvertidoCeleste" style="width: 63px">a. Labios</td>
				<td class="seccionBlanca">' . $k_labios[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 43px">d. Senos paranasales</td>
				<td class="seccionBlanca">' . $k_senos_paranasales[0] . '</td>
				<td style="width: 8px" class="textInvertidoCeleste">b. Pared abdominal</td>
				<td class="seccionBlanca">' . $k_parde_abdominal[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 79px">b. Miembros <br /> superiores</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_mie_superiores[0] . '</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste" style="width: 62px">b. Conjuntivas</td>
				<td class="seccionBlanca">' . $k_conjuntivas[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 63px">b. Lengua</td>
				<td class="seccionBlanca">' . $k_lengua[0] . '</td>
				<td style="width: 13px" rowspan="2" class="textInvertidoCeleste">6. Cuello</td>
				<td class="textInvertidoCeleste" style="width: 43px">a. Tiroides / masas</td>
				<td class="seccionBlanca">' . $k_tiroides[0] . '</td>
				<td rowspan="4" class="textInvertidoCeleste" style="width: 11px">10. Columna</td>
				<td style="width: 8px" class="textInvertidoCeleste">a. Flexibilidad</td>
				<td class="seccionBlanca">' . $k_flexibilidad[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 79px">c. Miembros <br /> inferiores</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_mie_inferiores[0] . '</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste" style="width: 62px">c. Pupilas</td>
				<td class="seccionBlanca">' . $k_pupilas[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 63px">c. Faringe</td>
				<td class="seccionBlanca">' . $k_faringe[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 43px">b. Movilidad</td>
				<td class="seccionBlanca">' . $k_movilidad[0] . '</td>
				<td style="width: 8px" rowspan="2" class="textInvertidoCeleste">b. Desviación</td>
				<td rowspan="2" class="seccionBlanca">' . $k_desviacion[0] . '</td>
				<td rowspan="4" class="textInvertidoCeleste" style="width: 52px">13. Neurológia</td>
				<td class="textInvertidoCeleste" style="width: 79px">a. Fuerza</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_fuerza[0] . '</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste" style="width: 62px">d. Córnea</td>
				<td class="seccionBlanca">' . $k_cornea[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 63px">d. Amígdalas</td>
				<td class="seccionBlanca">' . $k_amigdalas[0] . '</td>
				<td style="width: 13px" rowspan="2" class="textInvertidoCeleste">7. Tórax</td>
				<td class="textInvertidoCeleste" style="width: 43px">a. Mamas</td>
				<td class="seccionBlanca">' . $k_mamas[0] . '</td>
				<td class="textInvertidoCeleste" style="width: 79px">b. Sensibilidad</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_sensibilidad[0] . '</td>
			</tr>
			<tr>
				<td class="textInvertidoCeleste" style="height: 23px; width: 62px">e. Motilidad</td>
				<td style="height: 23px" class="seccionBlanca">' . $k_motilidad[0] . '</td>
				<td class="textInvertidoCeleste" style="height: 23px; width: 63px">e. Dentadura</td>
				<td style="height: 23px" class="seccionBlanca">' . $k_dentadura[0] . '</td>
				<td class="textInvertidoCeleste" style="height: 23px; width: 43px">b. Corazón</td>
				<td style="height: 23px" class="seccionBlanca">' . $k_corazon[0] . '</td>
				<td style="width: 8px; height: 23px" class="textInvertidoCeleste">c. Dolor</td>
				<td style="height: 23px" class="seccionBlanca">' . $k_dolor[0] . '</td>
				<td style="height: 23px; width: 79px" class="textInvertidoCeleste">c. Marcha</td>
				<td style="height: 23px; width: 80px" class="seccionBlanca">' . $k_marcha[0] . '</td>
			</tr>
			<tr>
				<td colspan="12" class="seccionBlanca">SI EXISTE EVIDENCIA DE PATOLOGÍA MARCAR CON &quot;X&quot; Y DESCRIBIR EN LA </td>
				<td class="textInvertidoCeleste" style="width: 79px">d. Reflejos</td>
				<td style="width: 80px" class="seccionBlanca">' . $k_reflejos[0] . '</td>
			</tr>
			<tr>
				<td colspan="15" style="height: 23px" class="seccionBlanca">' . $fo->k_observaciones . '</td>
			</tr>
		</table>

		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="3" class="headerMorado">L. RESULTADOS DE EXÁMENES GENERALES
				Y ESPECÍFICOS DE ACUERDO AL RIESGO Y PUESTO DE TRABAJO (IMAGEN,
				LABORATORIO Y OTROS)</td>
			</tr>
			<tr>
				<td class="HeaderVerde">EXAMEN</td>
				<td class="HeaderVerde">FECHA<br />
				(aaaa/mm/dd)</td>
				<td class="HeaderVerde">RESULTADOS</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca" style="height: 20px">' . $fo->l_examen . '</td>
				<td class="seccionBlanca" >' . $fo->l_fecha . '</td>
				<td class="seccionBlanca" >' . $fo->l_resultados . '</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca" style="height: 20px">' . $fo->l_examen2 . '</td>
				<td class="seccionBlanca">' . $fo->l_fecha2 . '</td>
				<td class="seccionBlanca">' . $fo->l_resultados2 . '</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca" style="height: 20px">' . $fo->l_examen3 . '</td>
				<td class="seccionBlanca">' . $fo->l_fecha3 . '</td>
				<td class="seccionBlanca">' . $fo->l_resultados3 . '</td>
			</tr>
			<tr class="seccionBlanca">
				<td class="seccionBlanca" style="height: 20px">' . $fo->l_examen4 . '</td>
				<td class="seccionBlanca">' . $fo->l_fecha4 . '</td>
				<td class="seccionBlanca">' . $fo->l_resultados4 . '</td>
			</tr>
			<tr>
				<td colspan="3" class="seccionBlanca">OBSERVACIONES:</td>
			</tr>
		</table>
		<table style="width: 100%" class="bordesTabla">
	<tr class="headerMorado">
		<td class="headerMorado">M. DIAGNÓSTICO</td>
		<td class="headerMorado">PRE= PRESUNTIVO DEF= DEFINITIVO </td>
		<td class="headerMorado">CIE</td>
		<td class="headerMorado">PRE</td>
		<td class="headerMorado">DEF</td>
	</tr>
	<tr>
		<td colspan="2" class="seccionBlanca">Diagnóstico 1: ' . $fo->m_diagnositico . '</td>
		<td class="seccionBlanca">' . $fo->m_cie . '</td>
		<td class="seccionBlanca">' . $m_pre[0] . '</td>
		<td class="seccionBlanca">' . $m_def[0] . '</td>
	</tr>
	<tr>
		<td colspan="2" class="seccionBlanca">Diagnóstico 2: ' . $fo->m_diagnositico2 . '</td>
		<td class="seccionBlanca">' . $fo->m_cie2 . '</td>
		<td class="seccionBlanca">' . $m_pre2[0] . '</td>
		<td class="seccionBlanca">' . $m_def2[0] . '</td>
	</tr>
	<tr>
		<td colspan="2" class="seccionBlanca">Diagnóstico 3: ' . $fo->m_diagnositico3 . '</td>
		<td class="seccionBlanca">' . $fo->m_cie3 . '</td>
		<td class="seccionBlanca">' . $m_pre3[0] . '</td>
		<td class="seccionBlanca">' . $m_def3[0] . '</td>
	</tr>
</table>
<table style="width: 100%" class="bordesTabla">
	<tr>
		<td colspan="8" class="headerMorado">N. APTITUD MÉDICA PARA EL TRABAJO</td>
	</tr>
	<tr>
		<td style="height: 13px" class="HeaderVerde">APTO</td>
		<td style="height: 13px" >' . $nAptoTrabajo[0] . '</td>
		<td style="height: 13px" class="HeaderVerde">APTO EN OBSERVACIÓN</td>
		<td style="height: 13px">' . $nAptoTrabajo[1] . '</td>
		<td style="height: 13px" class="HeaderVerde">APTO CON LIMITACIONES</td>
		<td style="height: 13px">' . $nAptoTrabajo[2] . '</td>
		<td style="height: 13px" class="HeaderVerde">NO APTO</td>
		<td style="height: 13px">' . $nAptoTrabajo[3] . '</td>
	</tr>
	<tr>
		<td class="HeaderVerde">Observación</td>
		<td colspan="7" class="seccionBlanca">' . $fo->n_observacion . '</td>
	</tr>
	<tr>
		<td class="HeaderVerde">Limitación</td>
		<td colspan="7" class="seccionBlanca">' . $fo->n_limitacion . '</td>
	</tr>
</table>


		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td class="headerMorado">O. RECOMENDACIONES Y/O TRATAMIENTO</td>
			</tr>
			<tr>
				<td class="seccionBlanca">' . $fo->o_recome_tratamiento . '</td>
			</tr>
		</table>

		<p class="seccionBlanca">CERTIFICO QUE LO ANTERIORMENTE EXPRESADO EN RELACIÓN A
		MI ESTADO DE SALUD ES VERDAD. SE ME HA INFORMADO LAS MEDIDAS PREVENTIVAS A TOMAR
		PARA DISMINUIR O MITIGAR LOS RIESGOS RELACIONADOS CON MI ACTIVIDAD LABORAL.</p>
		<table style="width: 100%" class="bordesTabla">
			<tr>
				<td colspan="10" class="headerMorado">P. DATOS DEL PROFESIONAL</td>
				<td style="width: 11px">&nbsp;</td>
				<td class="headerMorado">Q. FIRMA DEL USUARIO</td>
			</tr>
			<tr>
				<td class="HeaderVerde" style="width: 41px">FECHA<br />
				(aaaa/mm/dd)</td>
				<td style="width: 46px" class="seccionBlanca">' . $fechaModificacion . '</td>
				<td class="HeaderVerde" style="width: 20px">HORA</td>
				<td style="width: 76px" class="seccionBlanca">' . $horaModificacion . '</td>
				<td class="HeaderVerde" style="width: 90px">NOMBRES Y APELLIDOS</td>
				<td style="width: 156px" class="seccionBlanca">PABLO XAVIER MACHUCA CHIRIBOGA</td>
				<td class="HeaderVerde" style="width: 47px">CÓDIGO</td>
				<td style="width: 81px" class="seccionBlanca">' . $p->pac_id . ' ' . $fo->fo_id . '</td>
				<td class="HeaderVerde" style="width: 78px">FIRMA Y SELLO</td>
				<td style="width: 119px" class="seccionBlanca">&nbsp;</td>
				<td style="width: 11px">&nbsp;</td>
				<td class="seccionBlanca">&nbsp;</td>
			</tr>
		</table>


		</body>

		</html>


        ');



		// Save PDF on your public storage
		Storage::disk('public')->put($documentFileName, $document->Output($documentFileName, "S"));

		// Get file back from storage with the give header informations
		return Storage::disk('public')->download($documentFileName, 'Request', $header); //
	}


}
