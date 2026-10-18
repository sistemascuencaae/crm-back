<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\formularios2\CForm;
use App\Models\crm\formularios2\DForm;
use App\Models\crm\TipoCaso;
use App\Models\crm\TipoCasoFormulas;
use App\Models\crm\UltimoId;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UltimoIdController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' =>
        [
            'addCasoOscarBravo',
        ]]);
    }

    public function addCasoOscarBravo()
    {
        try {
            DB::transaction(function () {
                $parametro_ultimo_id = UltimoId::find(1);
                if (!$parametro_ultimo_id) {
                    throw new Exception("No se encontró el parámetro de último ID.");
                }

                $ultimo_id = $parametro_ultimo_id->id_externo;

                $formulaCaso = TipoCasoFormulas::find(33);
                if (!$formulaCaso) {
                    throw new Exception("No se encontró la fórmula del caso.");
                }

                $queryResultados = DB::SELECT("SELECT * from public.av_facturas_coninstalacion
                                                        WHERE ccm_id > ?
                                                    ", [$ultimo_id]);
                                                        // LIMIT 5

                $tipoCaso = TipoCaso::with([
                    'form' => fn($q) => $q->where('isactive', true),
                    'form.seccion' => fn($q) => $q->where('isactive', true)->orderBy('order'),
                    'form.seccion.campo' => fn($q) => $q->where('isactive', true)->orderBy('order')
                ])->find(147);

                if (!$tipoCaso || !$tipoCaso->form) {
                    throw new Exception("No se encontró el tipo de caso o formulario.");
                }

                foreach ($queryResultados as $row) {
                    // Crear CForm
                    $cform = CForm::create([
                        'form_id' => $tipoCaso->form->id
                    ]);

                    $respuestas = [];

                    foreach ($tipoCaso->form->seccion as $seccion) {
                        foreach ($seccion->campo as $campo) {
                        
                            $labelCampo = $campo->label;
                        
                            // Normalizamos por si viene con espacios o mayúsculas
                            foreach ($row as $columna => $valor) {
                                if (strtolower(trim($labelCampo)) === strtolower(trim($columna))) {
                                    DForm::create([
                                        'cform_id' => $cform->id,
                                        'field_id' => $campo->id,
                                        'seccion_id' => $seccion->id,
                                        'value' => $valor
                                    ]);
                                
                                    $respuestas[] = $valor;
                                    break; // Salir del inner loop una vez matchea
                                }
                            }
                        }
                    }

                    // Concatenar las respuestas como cadena
                    $datosConcatenados = implode(' ൠ ', $respuestas);

                    // Parsear tiempo vencimiento "HH:MM:SS"
                    list($horas, $minutos, $segundos) = explode(':', $formulaCaso->tiempo_vencimiento);
                    $fechaVencimiento = now()
                        ->addHours((int)$horas)
                        ->addMinutes((int)$minutos)
                        ->addSeconds((int)$segundos);

                    // Crear el objeto para el caso
                    $objeto_nuevo_caso = (object)[
                        "fas_id" => $formulaCaso->fase_id,
                        "user_id" => $formulaCaso->user_id,
                        "fecha_vencimiento" => $fechaVencimiento, // puedes ajustar esto
                        "prioridad" => $formulaCaso->prioridad,
                        "fase_anterior_id" => $formulaCaso->fase_id,
                        "tc_id" => $formulaCaso->tc_id,
                        "user_anterior_id" => $formulaCaso->user_id,
                        "user_creador_id" => $formulaCaso->user_id,
                        "estado_2" => $formulaCaso->estado_2,
                        "fase_creacion_id" => $formulaCaso->fase_id,
                        "tablero_creacion_id" => $formulaCaso->tab_id,
                        "dep_creacion_id" => $formulaCaso->dep_id,
                        "fase_anterior_id_reasigna" => $formulaCaso->fase_id,
                        "miembros" => [$formulaCaso->user_id],
                        "form_id2" => $cform->id, // se vincula con el CForm recién creado
                        "ent_id" => $row->ent_id,
                        "datos_formulario" => $datosConcatenados,
                        "fecha_inicio" => now(),
                        "orden" => 1,
                        "descripcion" => 'GENERADO AUTOMATICAMENTE',
                    ];

                    // Crear el caso usando el controlador
                    $casoController = new CasoController();
                    $requestSimulado = new Request((array)$objeto_nuevo_caso);
                    $respuestaCaso = $casoController->add($requestSimulado);

                    if (isset($respuestaCaso->original['status']) && $respuestaCaso->original['status'] != 'success') {
                        throw new Exception("Error al crear el caso: " . $respuestaCaso->original['message']);
                    }

                    $id_externo_mas_alto = collect($queryResultados)->max('ccm_id');

                    UltimoId::where('id', 1)->update([
                        'id_externo' => $id_externo_mas_alto
                    ]);

                    // agrego esto manualmente porque como el server los ejecuta automatico no me esta guardando ningun usuario creador para la bitacora
                    // por eso le mando el usuario que se ponga en la formula para la creación del caso
                    $usu_creador_addCaso = DB::SELECT("UPDATE crm.audits
                                                                SET user_id = $formulaCaso->user_id
                                                                WHERE caso_id = ? and accion = 'addCaso';", [$respuestaCaso->original['data']->id]);
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}