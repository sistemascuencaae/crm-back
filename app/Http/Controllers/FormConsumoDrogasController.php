<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\FormConsumoDrogas\ConsumoDroga;
use App\Models\FormConsumoDrogas\ConsumoFactores;
use App\Models\FormConsumoDrogas\FactoresPsConsumo;
use App\Models\FormConsumoDrogas\FormConsumoDrogas;
use App\Models\FormConsumoDrogas\FrecuenciaConsumo;
use App\Models\FormConsumoDrogas\ParametroConsumoDrogas;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormConsumoDrogasController extends Controller
{
    public function addFormConsumoDrogas(Request $request)
    {
        try {
            $result = DB::transaction(function () use ($request) {
                $drogaconsumidas = $request->input('drogaconsumidas');
                $factoresConsumo = $request->input('factoresConsumo');
                $newFormConsuDrogas = new FormConsumoDrogas();
                $newFormConsuDrogas->pac_id = $request->input('pac_id');
                $newFormConsuDrogas->cargo = $request->input('cargo');
                $newFormConsuDrogas->anio_nacimiento = $request->input('anioNacimiento');
                $newFormConsuDrogas->estadocivil = $request->input('estadocivil');
                $newFormConsuDrogas->genero = $request->input('genero');
                $newFormConsuDrogas->nivelinstruccion = $request->input('nivelinstruccion');
                $newFormConsuDrogas->numerohijos = $request->input('numerohijos');
                $newFormConsuDrogas->etnia = $request->input('etnia');
                $newFormConsuDrogas->discapacidad = $request->input('discapacidad');
                $newFormConsuDrogas->problemaconsumo = $request->input('problemaconsumo');
                $newFormConsuDrogas->tratamiento = $request->input('tratamiento');
                $newFormConsuDrogas->capacitacion = $request->input('capacitacion');
                $newFormConsuDrogas->otra_auto_etnica = $request->input('otra_auto_etnica');
                $newFormConsuDrogas->otra_droga = $request->input('otra_droga');
                $newFormConsuDrogas->otro_factor = $request->input('otro_factor');
                $newFormConsuDrogas->porcentaje_discapacidad = $request->input('porcentaje_discapacidad');
                $newFormConsuDrogas->save();
                foreach ($drogaconsumidas as $droga) {
                    $consumoDrogas = new ConsumoDroga();
                    $consumoDrogas->pcd_id = $droga['drogaId'];
                    $consumoDrogas->fcd_id = $droga['frecuenciaId'] ? $droga['frecuenciaId'] : 7;
                    $consumoDrogas->form_cd_id = $newFormConsuDrogas->id;
                    $consumoDrogas->droga_principal = $droga['principal'];
                    $consumoDrogas->save();
                }
                foreach ($factoresConsumo as $factores) {
                    $consumoFactores = new ConsumoFactores();
                    $consumoFactores->form_cons_dro_id = $newFormConsuDrogas->id;
                    $consumoFactores->faccons_id = $factores['facconsId'];
                    $consumoFactores->save();
                }

                return $newFormConsuDrogas;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Guardado con exito.', $result));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $th->getMessage()));
        }

        //REPORTE
        //select fcd.*, pcd.nombre, fc.nombre, pac.pac_segundo_nombre, fpc.nombre  from hclinico.paciente pac
        //inner join hclinico.form_consumo_drogas fcd on fcd.pac_id = pac.pac_id
        //inner join hclinico.consumo_droga cd on cd.form_cd_id = fcd.id
        //inner join hclinico.frecuencia_consumo fc on fc.id = cd.fcd_id
        //inner join hclinico.param_consu_drogas pcd on pcd.id = cd.pcd_id
        //inner join hclinico.consumo_factores cf on cf.form_cons_dro_id = fcd.id
        //inner join hclinico.factores_ps_consumo fpc on fpc.id = cf.faccons_id
    }


    public function listParametros()
    {
        try {
            $frecuencias = FrecuenciaConsumo::all();
            $paramsConsuDro = ParametroConsumoDrogas::all();
            $factoresPsConsumo = FactoresPsConsumo::all();
            $data = (object)[
                "frecuencias" => $frecuencias,
                "paramsConsuDro" => $paramsConsuDro,
                "factoresPsConsumo" => $factoresPsConsumo,
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener lista', $th->getMessage()));
        }
    }
    public function pacienteFormConsumoDro()
    {
        try {
            $data = Paciente::with(
                'formConsuDro.consumoDroga.frecuencia',
                'formConsuDro.consumoDroga.pramconsudroga',
                'formConsuDro.consumoFactores.factoresPsConsumo'

            )->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Guardado con exito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $th->getMessage()));
        }
    }
}
