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
use App\Models\crm\Miembros;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RobotCasoController extends Controller
{

    public function reasignarCaso($estadoFormId, $casoId, $tableroActualId)
    {
        try {
            $casoController = new CasoController();
            // Formula de ventas
            $formulaDestino = DB::selectOne("SELECT * from crm.estados_formulas where id = $estadoFormId");

            if ($formulaDestino) {

                $nombreTablero = DB::selectOne("SELECT ta.nombre  from crm.estados_formulas ef
                inner join crm.tablero ta on ta.id = ef.tablero_id 
                where ef.id = $estadoFormId");

                if ($nombreTablero) {
                    $parametro = DB::selectOne("SELECT * from crm.parametro where descripcion = 'Tablero del comite para aprobar creditos'"); // hace referencia al tablero comite

                    if ($nombreTablero->nombre == $parametro->nombre) { // valida que sea el tablero 'COMITE', por la descripcion que pusimos arriba 'Tablero del comite para aprobar creditos'

                        // // Formula destino de comite para que se vaya a ventas
                        $formDestino = DB::selectOne("select ef.* from crm.fase fa
                                                        inner join crm.estados_formulas ef on ef.fase_id_actual  = fa.id 
                                                        where fa.id = $formulaDestino->fase_id");

                        // validacion si no hay pedido en el caso que no envie el correo
                        if (Caso::find($casoId)->cpp_id !== null) {
                            $enviarCorreo = new EmailController();
                            $enviarCorreo->send_emailComite($formDestino->id, $casoId, $formDestino->tab_id);
                        } 

                    }

                }

            }
            $casoModificado = $this->validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId);
            $data = $casoController->getCaso($casoModificado->id);

            broadcast(new ReasignarCasoEvent($data));

            return response()->json(RespuestaApi::returnResultado('success', 'Reasignado con exito', $data));
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

    private function validacionReasignacionUsuario3($estadoFormId, $casoId, $tableroActualId, $facturaId, Request $request)
    {
        $emailController = new EmailController();
        $formula = EstadosFormulas::find($estadoFormId);
        //---validacion formual
        if (!$formula) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', 'La formula no existe.'));
        }
        $casoEnProceso = Caso::find($casoId);
        //---validacion caso en proceso
        if (!$casoEnProceso) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', 'El caso no existe.'));
        }
        //--- validaciones
        $casoEnProceso->user_anterior_id = $casoEnProceso->user_id;
        $casoEnProceso->fase_anterior_id = $casoEnProceso->fas_id;
        $casoEnProceso->fase_anterior_id_reasigna = $casoEnProceso->fas_id;
        $casoEnProceso->fas_id = $formula->fase_id;
        $casoEnProceso->estado_2 = $formula->est_id_proximo;
        $casoEnProceso->bloqueado = false;
        $casoEnProceso->bloqueado_user = '';
        //1.- si se mueve en mismo tablero
        if ($formula->tablero_id == $tableroActualId) {
            $casoEnProceso->save();
            return $casoEnProceso;
        }
        //2.- si el nuevo tablero es el tablero de usuario creador
        if ($formula->tablero_id == $casoEnProceso->tablero_creacion_id) {
            $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
            $casoEnProceso->save();
            $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
            $this->addMiembro($casoEnProceso->user_id, $casoId);
            return $casoEnProceso;
        }
        //3.- si el nuveo tablero es del usuario anterior
        $usuarioAnterior = DB::selectOne("SELECT us.* FROM crm.tablero_user tu
         inner join crm.users us on us.id = tu.user_id
         where us.id = ? and tu.tab_id = ?", [$casoEnProceso->user_anterior_id, $formula->tablero_id]);
        if ($usuarioAnterior) {
            $casoEnProceso->user_id = $usuarioAnterior->id;
            $casoEnProceso->save();
            $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
            return $casoEnProceso;
        }
        //4.- si es un tablero completamente nuevo
        $userGeneralNuevoTablero = DB::selectOne('SELECT u.* from crm.tablero_user tu
        inner join crm.users u on u.id = tu.user_id
        where u.usu_tipo = 1 and tu.tab_id = ? limit 1', [$formula->tablero_id]);
        $casoEnProceso->user_id = $userGeneralNuevoTablero->id;
        $casoEnProceso->save();
        $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
        return $casoEnProceso;
    }

    private function validacionReasignacionUsuarioUNo($estadoFormId, $casoId, $tableroActualId, $facturaId, Request $request)
    {
        $emailController = new EmailController();
        $formula = EstadosFormulas::find($estadoFormId);
        //---validacion formual
        if (!$formula) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', 'La formula no existe.'));
        }
        $casoEnProceso = Caso::find($casoId);
        //---validacion caso en proceso
        if (!$casoEnProceso) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', 'El caso no existe.'));
        }

        //---actualizacion de nueva fase y nuevo estado
        $casoEnProceso->user_anterior_id = $casoEnProceso->user_id;
        $casoEnProceso->fase_anterior_id = $casoEnProceso->fas_id;
        $casoEnProceso->fase_anterior_id_reasigna = $casoEnProceso->fas_id;
        $casoEnProceso->fas_id = $formula->fase_id;
        $casoEnProceso->estado_2 = $formula->est_id_proximo;
        $casoEnProceso->bloqueado = false;
        $casoEnProceso->bloqueado_user = '';

        $caso = Caso::find($casoId);
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
        $audit->auditable_id = $caso->id;
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
        // start diferencia de tiempos en horas minutos y segundos
        $CasoController = new CasoController();
        $tipo = 2;
        $CasoController->calcularTiemposCaso(
            $casoEnProceso,
            $casoEnProceso->id,
            $casoEnProceso->estado_2,
            $casoEnProceso->fas_id,
            $tipo,
            $casoEnProceso->user_id
        );
        // end diferencia de tiempos en horas minutos y segundos



        /*---------******** ANALISIS DE USUARIOS ********------------- */
        //---Si el nuevo tablero es el tablero de creacio reasigna al creador
        if ($formula->tablero_id == $casoEnProceso->tablero_creacion_id) {
            //---pregunta si el usuario sigue en el tablero
            $userTab = $this->getUsuarioTablero($casoEnProceso->user_creador_id, $casoEnProceso->tablero_creacion_id);
            if ($userTab) {
                $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
                $casoEnProceso->save();

                $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
                $this->addMiembro($casoEnProceso->user_id, $casoId);

                return $casoEnProceso;
            }
        }

        //EL TABLERO ES EL USUARIO ANTERIOR
        $usuarioAnterior = DB::selectOne("SELECT * FROM crm.tablero_user tu
         inner join crm.users us on us.id = tu.user_id
         where tu.user_id = ? and tu.tab_id = ? and us.en_linea = true", [$casoEnProceso->user_anterior_id, $formula->tablero_id]);
        if ($usuarioAnterior) {
            $casoEnProceso->user_id = $usuarioAnterior->user_id;
            $casoEnProceso->save();
            return $casoEnProceso;
        }


        $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
        return $casoEnProceso;
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
