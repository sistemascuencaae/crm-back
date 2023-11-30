<?php

namespace App\Http\Controllers\crm\credito;

use App\Events\ReasignarCasoEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
use App\Http\Controllers\crm\EmailController;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Caso;
use App\Models\crm\ControlTiemposCaso;
use App\Models\crm\EstadosFormulas;
use App\Models\crm\Miembros;
use App\Models\crm\RequerimientoCaso;
use App\Models\crm\Tablero;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RobotCasoController extends Controller
{

    public function reasignarCaso(Request $request)
    {
        try {
            $casoController = new CasoController();
            $estadoFormId = $request->input('estadoFormId');
            $casoId = $request->input('casoId');
            $tableroActualId = $request->input('tableroActualId');
            $facturaId = $request->input('facturaId');
            $casoModificado = $this->validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId, $facturaId, $request);
            $data = $casoController->getCaso($casoModificado->id);
            broadcast(new ReasignarCasoEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'Reasignado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al reasignar', $e));
        }
    }

    private function validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId, $facturaId, Request $request)
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

        /*---------******** ADD REQUERIMIENTOS AL CASO ********------------- */
        $casoController = new CasoController();
        $casoController->addRequerimientosFase($casoEnProceso->id, $casoEnProceso->fas_id, $casoEnProceso->user_creador_id);

        /*---------******** ANALISIS DE USUARIOS ********------------- */
        //---Si el nuevo tablero es el tablero de creacio reasigna al creador
        if ($formula->tablero_id == $casoEnProceso->tablero_creacion_id) {
            //---pregunta si el usuario sigue en el tablero
            $userTab = $this->getUsuarioTablero($casoEnProceso->user_creador_id, $casoEnProceso->tablero_creacion_id);
            if ($userTab) {
                $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
                $casoEnProceso->save();

                $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
                //$casoController->enviarCorreoCliente($casoEnProceso->id);
                $this->addMiembro($casoEnProceso->user_id, $casoId);
                return $casoEnProceso;
            }
        }
        //--- usuarios en linea del nuevo tablero
        $usuariosNuevoTablero = DB::select("SELECT * FROM crm.usuarios_casos WHERE tab_id = ?", [$formula->tablero_id]);
        $userMenorNumCasos = $this->organizarCasos($usuariosNuevoTablero);
        $this->addMiembro($userMenorNumCasos->usu_id, $casoId);
        $casoEnProceso->user_id = $userMenorNumCasos->usu_id;
        $casoEnProceso->save();
        $emailController->send_emailCambioFase($casoEnProceso->id, $casoEnProceso->fas_id);
        return $casoEnProceso;

        // //------------------------------------------------------------
        // //-------------------OPCION 1---------------------------------
        // //------------------------------------------------------------
        // //---Si el nuevo tablero es el tablero de creacio reasigna al creador
        // if ($formula->tablero_id == $casoEnProceso->tablero_creacion_id) {
        //     //---pregunta si el usuario sigue en el tablero
        //     $userTab = DB::selectOne('SELECT * FROM crm.tablero_user where user_id = ? and tab_id = ?', [$casoEnProceso->user_creador_id, $casoEnProceso->tablero_creacion_id]);
        //     if ($userTab) {
        //         $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
        //     } else {
        //         //--- si es no, asignar al usuario general del tablero de creacion
        //         $usuGeneral = DB::selectOne('SELECT * FROM crm.users where tab_id = ? and usu_tipo = 1;', [$formula->tablero_id]);
        //         $casoEnProceso->user_id = $usuGeneral->id;
        //     }
        //     $casoEnProceso->save();
        //     return $casoEnProceso;
        // }
        // //---Usuarios en linea del nuevo tablero y sus perfiles
        // $usuariosNuevoTablero = DB::select("SELECT usu.id as usu_id,usu.usu_tipo, usu.name, usu.usu_tipo_analista, pa.nombre, pa.monto_inicial, pa.monto_limite from crm.tablero tab
        // inner join crm.tablero_user tu on tu.tab_id = tab.id
        // inner join crm.users usu on usu.id = tu.user_id
        // left join crm.perfil_analistas pa on pa.id = usu.usu_tipo_analista
        // where tab.id = ? and usu.estado = true and usu.en_linea = true", [$formula->tablero_id]);

        // //---Temporalmente enviar al usuario general
        // foreach ($usuariosNuevoTablero as $key => $value) {
        //     if ($value->usu_tipo == 1) {
        //         $casoEnProceso->user_id = $value->usu_id;
        //         $casoEnProceso->save();
        //         return $casoEnProceso;
        //         break;
        //     }
        // }
        // return $casoEnProceso;

        // //------------------------------------------------------------
        // //-------------------OPCION 2---------------------------------
        // //------------------------------------------------------------
        // //---Si el nuevo tablero NO ES el tablero de creacio buscar en usuario anterior agrega solo si el usuario es el anterior
        // if ($formula->tablero_id != $casoEnProceso->tablero_creacion_id) {
        //     foreach ($usuariosNuevoTablero as $key => $value) {
        //         if ($value->usu_id == $casoEnProceso->user_anterior_id) {
        //             $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
        //             //$casoEnProceso->save();
        //             return $casoEnProceso;
        //         }
        //     }
        // }
        // //------------------------------------------------------------
        // //-------------------OPCION 3---------------------------------
        // //------------------------------------------------------------
        // //echo ('test: 2 -> no es el tablero de creacion');
        // //--- Buscar usuarios del nuevo tablero
        // // $newTablero = Tablero::find($formula->tablero_id)->with('tableroUsuario.usuario.perfil_analista')->firstOrFail();
        // // $newTableroToString = json_encode($newTablero);
        // // $tablero = json_decode($newTableroToString);
        // // $usuariosNuevoTablero = $tablero->tablero_usuario;

        // //---Analizar perfil de analista
        // //---Si ya tenemos la factura o pedido
        // $userCumplenPerfil = null;
        // $facturaId != null ?? $userCumplenPerfil = $this->analizarPerfilAnalista($usuariosNuevoTablero);
        // //--tengo todos los usuarios que estan en linea del nuevo tablero
        // //echo ('$usuariosNuevoTablero: '.json_encode($usuariosNuevoTablero));
        // //return $usuariosNuevoTablero;
        // //---Valido usuario anterior del caso esta en linea --------------------------------   2
        // $userAnterior = User::find($casoEnProceso->user_anterior_id);


        // //--- retorna opcion 1 hasta el momento
    }

    private function analizarPerfilAnalista($usuarios)
    {
    }

    private function analizarCasosPorUsario($usuarios)
    {
        $casosUsuario = new Collection();

        //2068
        //2067
        //2066
    }


    public function getUsuarioTablero($userCreadorId, $tableroCreacionId)
    {
        $userTab = DB::selectOne('SELECT * FROM crm.tablero_user where user_id = ? and tab_id = ?', [$userCreadorId, $tableroCreacionId]);
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
        $userExiste = DB::selectOne("SELECT * from crm.miembros m where m.user_id = ? and m.caso_id = ?", [$userId, $casoId]);
        if (!$userExiste) {
            $miembro = new Miembros();
            $miembro->user_id = $userId;
            $miembro->caso_id = $casoId;
            $miembro->save();
        }
    }


}
