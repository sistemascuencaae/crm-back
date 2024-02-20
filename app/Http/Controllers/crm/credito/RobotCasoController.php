<?php

namespace App\Http\Controllers\crm\credito;

use App\Events\ReasignarCasoEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
use App\Http\Controllers\crm\EmailController;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Caso;
use App\Models\crm\EstadosFormulas;
use App\Models\crm\Fase;
use App\Models\crm\Miembros;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RobotCasoController extends Controller
{

    public function reasignarCaso($estadoFormId, $casoId, $tableroActualId, $banMostrarVistaCreditoAprobado = null)
    {
        try {
            $result = DB::transaction(function () use ($estadoFormId, $casoId, $tableroActualId, $banMostrarVistaCreditoAprobado) {



                $casoController = new CasoController();

                // Sacamos la formula de la fase actual por el ID
                $formulaDestino = EstadosFormulas::where('id', $estadoFormId)
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima')
                    ->first();

                if ($formulaDestino) {

                    // Saco el nombre del tablero_proximo, en este caso seria COMITE
                    $nombreTablero = $formulaDestino->tablero_proximo->nombre;

                    if ($nombreTablero) {

                        // Saco los parametros del COMITE por abreviacion en la tabla parametro
                        $parametro = DB::table('crm.parametro')
                            ->where('abreviacion', 'TC')
                            ->first();

                        if ($parametro) {
                            // Valida que sea el tablero 'COMITE', por el valor del parametro (valor = 'COMITE')
                            if ($nombreTablero == $parametro->valor) {

                                // Validacion si no hay pedido en el caso que no envie el correo
                                if (Caso::find($casoId)->cpp_id !== null) {
                                    $enviarCorreo = new EmailController();
                                    $enviarCorreo->send_emailComite($formulaDestino->tablero_proximo->id, $casoId);
                                }
                            }
                        }
                    }
                }

                $casoModificado = $this->validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId);
                $data = $casoController->getCaso($casoModificado->id);

                broadcast(new ReasignarCasoEvent($data));

                // si existe la variable banMostrarVistaCreditoAprobado, se muestra la vista de caso creditoAprobado
                if ($banMostrarVistaCreditoAprobado == 1) {
                    return view('mail.creditoAprobado');
                } else if ($banMostrarVistaCreditoAprobado == 2) {
                    return view('mail.creditoRechazado');
                } else {
                    return $data;//response()->json(RespuestaApi::returnResultado('success', 'Reasignado con exito', $data));
                }
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Reasignado con exito', $result));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al reasignar', $e));

        }
    }

    public function validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId)
    {
        $emailController = new EmailController();
        $casoEnProceso = Caso::find($casoId);
        $formula = EstadosFormulas::find($estadoFormId);
        if (!$casoEnProceso) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', 'El caso no existe.'));
        }
        if (!$formula) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', 'La formula no existe.'));
        }
        //--- APROBAR CREDITO
        if ($casoEnProceso->cpp_id) {
            //--- Verificar si esta fase es de aprobacion de credito
            $faseNueva = Fase::find($formula->fase_id);
            if ($faseNueva) {
                if ($faseNueva->aprobar_credito == true) {
                    DB::update("UPDATE public.cpedido_proforma set ecr_id=(select ecr_id from public.estado_credito where ecr_codigo='APR')
                    where cpp_id = ?", [$casoEnProceso->cpp_id]);
                }
            }
        }
        //--- datos anteriores
        $userAnteriorId = $casoEnProceso->user_id;
        $faseAnteriorId = $casoEnProceso->fas_id;
        //--- validaciones
        $casoEnProceso->user_anterior_id = $casoEnProceso->user_id;
        $casoEnProceso->fase_anterior_id = $casoEnProceso->fas_id;
        $casoEnProceso->fase_anterior_id_reasigna = $casoEnProceso->fas_id;
        $casoEnProceso->fas_id = $formula->fase_id;
        $casoEnProceso->estado_2 = $formula->est_id_proximo;
        $casoEnProceso->bloqueado = false;
        $casoEnProceso->bloqueado_user = '';

        // AUDITORIA
        $request = RequestFacade::instance();

        // $caso = Caso::find($casoId);
        $casoAudit = Caso::with(
            'user',
            'userCreador',
            'clienteCrm',
            'fase.tablero',
            'estadodos'
        )->find($casoId); // Solo para el audits NADA MAS

        $audit = new Audits();
        // Obtener el old_values (valor antiguo)
        $valorAntiguo = $casoAudit;
        $audit->old_values = json_encode($valorAntiguo); // json_encode para convertir en string ese array
        // START Bloque de código que genera un registro de auditoría manualmente
        $audit->user_id = Auth::id();
        $audit->event = 'updated';
        $audit->auditable_type = Caso::class;
        $audit->auditable_id = $casoAudit->id;
        $audit->user_type = User::class;
        $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
        $audit->url = $request->fullUrl();
        $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
        $audit->estado_caso = $casoEnProceso->estadodos->nombre;
        $audit->estado_caso_id = $casoEnProceso->estado_2;
        $audit->accion = 'cambioEstado';
        // Establecer old_values y new_values
        $audit->new_values = json_encode($casoEnProceso); // json_encode para convertir en string ese array
        $audit->save();
        // END Auditoria
        /*---------******** ADD REQUERIMIENTOS AL CASO ********------------- */
        $casoController = new CasoController();
        $casoController->addRequerimientosFase($casoEnProceso->id, $casoEnProceso->fas_id, $casoEnProceso->user_creador_id);
        //0.-  si el caso esta en la bandeja de entrada con el usuario general
        if ($formula->tablero_id == $tableroActualId) {
            $casoBandejaEntrada = DB::selectOne("SELECT fa.nombre, fa.orden, u.name from crm.caso ca
            inner join crm.users u on u.id = ca.user_id
            inner join crm.fase fa on fa.id = ca.fas_id
            where u.usu_tipo = 1 and fa.fase_tipo = 1 and ca.id = $casoId");
            if ($casoBandejaEntrada) {
                $user_id = Auth::id();
                if ($user_id) {
                    $casoEnProceso->user_id = $user_id;
                    $casoEnProceso->save();
                    $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
                    $this->calcularTiemposCaso($casoEnProceso);
                    return $casoEnProceso;
                }
            }
        }
        // si se mueve en el mismo tablero y se encuentra asignado un usuario
        if ($formula->tablero_id == $tableroActualId) {
            $casoEnProceso->save();
            $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
            $this->calcularTiemposCaso($casoEnProceso);
            return $casoEnProceso;
        }
        //1.- si el nuevo tablero es el tablero de usuario creador
        if ($formula->tablero_id == $casoEnProceso->tablero_creacion_id) {
            $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
            $casoEnProceso->save();
            $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
            $this->calcularTiemposCaso($casoEnProceso);
            return $casoEnProceso;
        }
        //2.- Asignar al usuario anterior si es que existe en el control de tiempos
        $controlTiemposCaso = DB::selectOne("SELECT
            ctc.caso_id,
            ctc.user_id,
            U.usu_tipo,
            ctc.tab_id,
            ctc.fase_id
            FROM crm.control_tiempos_caso ctc
            inner join crm.users u on u.id = ctc.user_id
            WHERE ctc.caso_id = ? and u.usu_tipo <> 1 and ctc.tab_id = ? order by ctc.created_at desc limit 1", [$casoId, $formula->tablero_id]);
        if ($controlTiemposCaso) {
            $casoEnProceso->user_id = $controlTiemposCaso->user_id;
            $casoEnProceso->save();
            $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
            $this->calcularTiemposCaso($casoEnProceso);
            return $casoEnProceso;
        }
        //3.- asignar al usuario general
        $userGeneralNuevoTablero = DB::selectOne('SELECT u.* from crm.tablero_user tu
        inner join crm.users u on u.id = tu.user_id
        where u.usu_tipo = 1 and tu.tab_id = ? limit 1', [$formula->tablero_id]);
        $casoEnProceso->user_id = $userGeneralNuevoTablero->id;
        $casoEnProceso->save();
        $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
        $this->calcularTiemposCaso($casoEnProceso);
        return $casoEnProceso;
    }

    public function calcularTiemposCaso($caso)
    {
        $CasoController = new CasoController();
        $tipo = 2;
        $CasoController->calcularTiemposCaso(
            $caso,
            $caso->id,
            $caso->estado_2,
            $caso->fas_id,
            $tipo,
            $caso->user_id
        );
    }

    public function getUsuarioTablero($userCreadorId, $tableroCreacionId)
    {
        $userTab = DB::selectOne('SELECT * FROM crm.tablero_user tu
         inner join crm.users u on u.id = tu.user_id
         where tu.user_id = ? and tu.tab_id = ?', [$userCreadorId, $tableroCreacionId]);
        return $userTab;
    }


    public function organizarCasos($usuarios)
    {
        //ordenar el el usuario que tiene menor numero de casos
        usort($usuarios, function ($a, $b) {
            return $a->numero_casos - $b->numero_casos;
        });
        $userMenorNumCasos = $usuarios[0];
        return $userMenorNumCasos;
    }

    public function addMiembro($userId, $casoId)
    {
        $userGeneral = DB::selectOne('SELECT * from crm.users WHERE id = ? and usu_tipo = 1', [$userId]);
        if (!$userGeneral) {
            $userExiste = DB::selectOne("SELECT * from crm.miembros m where m.user_id = ? and m.caso_id = ?", [$userId, $casoId]);
            if (!$userExiste) {
                $miembro = new Miembros();
                $miembro->user_id = $userId;
                $miembro->caso_id = $casoId;
                $miembro->save();
            }
        }
    }
}
