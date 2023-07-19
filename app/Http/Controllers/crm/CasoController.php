<?php

namespace App\Http\Controllers\crm;

use App\Events\TableroEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\ChatGroups;
use App\Models\crm\Caso;
use App\Models\crm\DTipoTarea;
use App\Models\crm\Miembros;
use App\Models\crm\Tareas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CasoController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function add(Request $request)
    {
        $casoInput = $request->all();
        $miembros = $request->input('miembros');


        try {
            $casoCreado = DB::transaction(function () use ($casoInput, $miembros) {
                $caso = new Caso($casoInput);
                $caso->save();

                //buscar las tareas predefinidas
                $arrayDtipoTareas = DTipoTarea::where('ctt_id', $caso->ctt_id)->get();

                //insertar en la tabla tareas
                foreach ($arrayDtipoTareas as $dtt) {
                    $tarea = new Tareas();
                    $tarea->nombre = $dtt->nombre;
                    $tarea->requerido = $dtt->requerido;
                    $tarea->estado = $dtt->estado;
                    $tarea->ctti_id = $caso->ctt_id;
                    $tarea->marcado = false;
                    $caso->tareas()->save($tarea);
                }



                $newGrupo = new ChatGroups();
                $newGrupo->nombre = 'GRUPO CASO '.$caso->id;
                $newGrupo->uniqd = 'caso.grupo.'.$caso->id;
                $newGrupo->save();

                $caso->nombre = 'CASO # '.$caso->id;
                $caso->descripcion = 'CASO # '.$caso->id;
                $caso->save();

                for ($i=0; $i < sizeof($miembros); $i++) {
                    $miembro = new Miembros();
                    $miembro->user_id = $miembros[$i];
                    $miembro->chat_group_id = $newGrupo->id;
                    $caso->miembros()->save($miembro);
                }
                return $caso;
            });

            $data = Caso::with('user', 'entidad', 'resumen')->where('id', $casoCreado['id'])->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Caso creado con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar datos', $th->getMessage()));
        }






        // try {
        //     $result = Caso::create($request->all());
        //     $tareas = $request->input('tarea');
        //     $data = Caso::with('user', 'entidad', 'resumen')->where('id', $result['id'])->first();
        //     return response()->json(RespuestaApi::returnResultado('success', 'Caso creado con exito', $data));
        // } catch (\Throwable $th) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar datos', $th->getMessage()));
        // }
    }

    public function list()
    {
        $data = Caso::with('caso.user', 'caso.entidad')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
    }
    public function casoById($id)
    {
        $data = Caso::with('user', 'entidad', 'resumen')->where('id', $id)->first();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
    }
    public function editFase(Request $request)
    {

        try {
            $caso = Caso::find($request->input('id'));
            $caso->update([
                'fas_id' => $request->input('fas_id'),
                'fase_anterior_id' => $request->input('fase_anterior_id'),
            ]);
            $data = $this->getCasoJoinTablero($caso->id);
            //$data = Caso::with('user','entidad', 'resumen', 'tareas','actividad')->where('id',$caso->id)->get();
            //echo('ESTA ES LA DATA:'.json_encode($data));
            broadcast(new TableroEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'El caso se actualizo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()));
        }
    }

    public function listCasoById($id)
    {
        $data = Caso::with('user', 'entidad', 'cTipoTarea.dTipoTarea')->where('id', $id)->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El caso se listo con éxito', $data));
    }
    public function bloqueoCaso(Request $request)
    {
        $data = [];
        $casoId = $request->input("casoId");
        $tabId = $request->input("tableroId");
        $userId = $request->input("userId");
        $bloqueado = $request->input("bloqueado");
        $bloqueado_user = $request->input("bloqueado_user");
        $caso = Caso::find($casoId);
        if ($caso) {
            $caso->bloqueado = $bloqueado;
            $caso->bloqueado_user = $bloqueado_user;
            $caso->save();
            $data = $this->getCasoJoinTablero($casoId);
        }
        broadcast(new TableroEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }


    private function getCasoJoinTablero($casoId)
    {
        $data = DB::select('SELECT ca.*, ta.id as tablero_id FROM public.users us
        inner join crm.caso ca on ca.user_id = us.id
        INNER JOIN crm.fase fa on fa.id = ca.fas_id
        INNER JOIN crm.tablero ta on ta.id = fa.tab_id
        where ca.id = ' . $casoId);
        return $data;
    }
}
