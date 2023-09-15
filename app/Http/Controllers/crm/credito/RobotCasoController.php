<?php

namespace App\Http\Controllers\crm\credito;

use App\Events\ReasignarCasoEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use App\Models\crm\EstadosFormulas;
use App\Models\crm\Tablero;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $casoEnProceso = Caso::find($casoId);
            $casoModificado = $this->validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId, $facturaId);
            // return response()->json([
            //     "antes" => $casoEnProceso,
            //     "despues" => $casoModificado
            // ]);
            // return $this->validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId, $facturaId);
            //----------------------------------------------------------------FALATA EL NUEVO USUARIO ASIGNADO
            //$casoEnProceso->save();
            $data = $casoController->getCaso($casoModificado->id);
            broadcast(new ReasignarCasoEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'Reasignado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al reasignar', $e));
        }
    }

    private function validacionReasignacionUsuario($estadoFormId, $casoId, $tableroActualId, $facturaId)
    {

        '"antes": {
        "id": 562,
        "fas_id": 322,
        "user_id": 2066,
        "prioridad": 2,
        "bloqueado": true,
        "bloqueado_user": "Credito 1",
        "fase_anterior_id": 321,
        "tc_id": 55,
        "user_anterior_id": 2066,
        "user_creador_id": 2066,
        "estado_2": 36,
        "fase_creacion_id": 321,
        "tablero_creacion_id": 157,
        "dep_creacion_id": 1,
        "fase_anterior_id_reasigna": 321
        },';

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
        //------------------------------------------------------------
        //-------------------OPCION 1---------------------------------
        //------------------------------------------------------------
        //---Si el nuevo tablero es el tablero de creacio reasigna al creador
        //echo ('test: 1-> usuario creador activo tablero de creacion');
        if ($formula->tablero_id == $casoEnProceso->tablero_creacion_id) {
            //---pregunta si el usuario sigue en el tablero
            $userTab = DB::selectOne('SELECT * FROM crm.tablero_user where user_id = ? and tab_id = ?', [$casoEnProceso->user_creador_id, $casoEnProceso->tablero_creacion_id]);
            if ($userTab) {
                $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
            } else {
                //--- si es no, asignar al usuario general del tablero de creacion
                $usuGeneral = DB::selectOne('SELECT * FROM public.users where tab_id = ? and usu_tipo = 1;', [$formula->tablero_id]);
                $casoEnProceso->user_id = $usuGeneral->id;
            }
            $casoEnProceso->save();
            return $casoEnProceso;
        }


        $temp = '{
	{
		"id" : 2,
		"name" : "MARIUXI LEMA",
		"usu_tipo_analista" : 1,
		"monto_inicial" : 0,
		"monto_limite" : 0
	},
	{
		"id" : 2068,
		"name" : "Credito 3",
		"usu_tipo_analista" : 3,
		"monto_inicial" : 1,
		"monto_limite" : 3500
	},
	{
		"id" : 2116,
		"name" : "USUARIO GENERAL TEST CREDITO 3 159",
		"usu_tipo_analista" : 1,
		"monto_inicial" : 0,
		"monto_limite" : 0
	}
    ]}
    ';
        //---Usuarios en linea del nuevo tablero y sus perfiles
        $usuariosNuevoTablero = DB::select("SELECT usu.id as usu_id,usu.usu_tipo, usu.name, usu.usu_tipo_analista, pa.nombre, pa.monto_inicial, pa.monto_limite from crm.tablero tab
        inner join crm.tablero_user tu on tu.tab_id = tab.id
        inner join public.users usu on usu.id = tu.user_id
        inner join crm.perfil_analistas pa on pa.id = usu.usu_tipo_analista
        where tab.id = ? and usu.estado = true and usu.en_linea = true", [$formula->tablero_id]);

        //---Temporalmente enviar al usuario general
        foreach ($usuariosNuevoTablero as $key => $value) {
            if ($value->usu_tipo == 1) {
                $casoEnProceso->user_id = $value->usu_id;
                $casoEnProceso->save();
                return $casoEnProceso;
                break;
            }
        }

        return $casoEnProceso;

        //------------------------------------------------------------
        //-------------------OPCION 2---------------------------------
        //------------------------------------------------------------
        //---Si el nuevo tablero NO ES el tablero de creacio buscar en usuario anterior agrega solo si el usuario es el anterior
        if ($formula->tablero_id != $casoEnProceso->tablero_creacion_id) {
            foreach ($usuariosNuevoTablero as $key => $value) {
                if ($value->usu_id == $casoEnProceso->user_anterior_id) {
                    $casoEnProceso->user_id = $casoEnProceso->user_creador_id;
                    //$casoEnProceso->save();
                    return $casoEnProceso;
                }
            }
        }
        //------------------------------------------------------------
        //-------------------OPCION 3---------------------------------
        //------------------------------------------------------------
        //echo ('test: 2 -> no es el tablero de creacion');
        //--- Buscar usuarios del nuevo tablero
        // $newTablero = Tablero::find($formula->tablero_id)->with('tableroUsuario.usuario.perfil_analista')->firstOrFail();
        // $newTableroToString = json_encode($newTablero);
        // $tablero = json_decode($newTableroToString);
        // $usuariosNuevoTablero = $tablero->tablero_usuario;

        //---Analizar perfil de analista
        //---Si ya tenemos la factura o pedido
        $userCumplenPerfil = null;
        $facturaId != null ?? $userCumplenPerfil = $this->analizarPerfilAnalista($usuariosNuevoTablero);













        //--tengo todos los usuarios que estan en linea del nuevo tablero
        //echo ('$usuariosNuevoTablero: '.json_encode($usuariosNuevoTablero));
        //return $usuariosNuevoTablero;
        //---Valido usuario anterior del caso esta en linea --------------------------------   2
        $userAnterior = User::find($casoEnProceso->user_anterior_id);


        //--- retorna opcion 1 hasta el momento
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
}
