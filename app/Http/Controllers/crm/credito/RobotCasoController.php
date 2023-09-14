<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
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


        //try {

        $estadoFormId = $request->input('estadoFormId');
        $casoId = $request->input('casoId');


        $casoEnProceso = Caso::find($casoId);
        //$casoModificado = $this->validacionReasignacionUsuario($estadoFormId, $casoId);
        // return response()->json([
        //     "antes" => $casoEnProceso,
        //     "despues" => $casoModificado
        // ]);

        return $this->validacionReasignacionUsuario($estadoFormId, $casoId);


        //----------------------------------------------------------------FALATA EL NUEVO USUARIO ASIGNADO


        //$casoEnProceso->save();
        //$data = $this->getCaso($casoEnProceso->id);
        //broadcast(new ReasignarCasoEvent($data));
        // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $dataInput));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }

    }

    private function validacionReasignacionUsuario($estadoFormId, $casoId)
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
            //$casoEnProceso->save();
            return $casoEnProceso;
        }
        //------------------------------------------------------------
        //-------------------OPCION 2---------------------------------
        //------------------------------------------------------------
        //echo ('test: 2 -> no es el tablero de creacion');
        //--- Buscar usuarios del nuevo tablero
        $newTablero = Tablero::find($formula->tablero_id)->with('tableroUsuario.usuario.perfil_analista')->firstOrFail();
        $newTableroToString = json_encode($newTablero);
        $tablero = json_decode($newTableroToString);
        $usuariosNuevoTablero = $tablero->tablero_usuario;

        foreach ($usuariosNuevoTablero as $tu) {
            //--usuarios en lienea
            if($tu->usuario->en_linea){











                echo ('$tu->usuario: '.json_encode($tu->usuario));
            }
        }


        //--tengo todos los usuarios que estan en linea del nuevo tablero
        //echo ('$usuariosNuevoTablero: '.json_encode($usuariosNuevoTablero));
        //return $usuariosNuevoTablero;
        //---Valido usuario anterior del caso esta en linea --------------------------------   2
        $userAnterior = User::find($casoEnProceso->user_anterior_id);


        //--- retorna opcion 1 hasta el momento
    }
}
