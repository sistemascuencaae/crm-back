<?php

namespace App\Http\Controllers\crm;

use App\Events\TableroEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Resources\RespuestaApi2;
use App\Models\ChatGroups;
use App\Models\crm\Caso;
use App\Models\crm\DTipoTarea;
use App\Models\crm\Fase;
use App\Models\crm\Miembros;
use App\Models\crm\Tareas;
use App\Models\Miembros as ModelsMiembros;
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
                    $tarea->ctt_id = $caso->ctt_id;
                    $tarea->marcado = false;
                    $caso->tareas()->save($tarea);
                }



                $newGrupo = new ChatGroups();
                $newGrupo->nombre = 'GRUPO CASO ' . $caso->id;
                $newGrupo->uniqd = 'caso.grupo.' . $caso->id;
                $newGrupo->save();

                $caso->nombre = 'CASO # ' . $caso->id;
                $caso->descripcion = 'CASO # ' . $caso->id;
                $caso->save();

                for ($i = 0; $i < sizeof($miembros); $i++) {
                    $miembro = new Miembros();
                    $miembro->user_id = $miembros[$i];
                    $miembro->chat_group_id = $newGrupo->id;
                    $caso->miembros()->save($miembro);
                }

                return Caso::with('user', 'entidad', 'resumen', 'tareas', 'actividad', 'Etiqueta', 'miembros.usuario.departamento', 'Galeria', 'Archivo')->where('id', $caso->id)->first();
            });

            //$data = $this->getCasoJoinTablero($casoCreado->id);
            //$data = Caso::with('user','entidad', 'resumen', 'tareas','actividad')->where('id',$caso->id)->get();
            //echo('ESTA ES LA DATA:'.json_encode($data));
            broadcast(new TableroEvent($casoCreado));

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $casoCreado));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar datos', $th->getMessage()));
        }
    }

    public function list()
    {
        $data = Caso::with('caso.user', 'caso.entidad')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigio con exito', $data));
    }
    public function casoById($id)
    {
        $data = Caso::with('user', 'entidad', 'resumen')->where('id', $id)->first();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigio con exito', $data));
    }
    public function editFase(Request $request)
    {

        try {
            $caso = Caso::find($request->input('id'));
            $caso->update([
                'fas_id' => $request->input('fas_id'),
                'fase_anterior_id' => $request->input('fase_anterior_id'),
            ]);
            //$data = $this->getCasoJoinTablero($caso->id);
            $data = Caso::with('user', 'entidad', 'resumen', 'tareas', 'actividad', 'Etiqueta', 'miembros', 'Galeria', 'Archivo')->where('id', $caso->id)->first();

            //echo('ESTA ES LA DATA:'.json_encode($data));
            broadcast(new TableroEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'El caso se actualizo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()));
        }
    }

    public function listCasoById($id)
    {
        // $data = Caso::with('user', 'entidad', 'cTipoTarea.dTipoTarea')->where('id', $id)->get();
        // return response()->json(RespuestaApi::returnResultado('success', 'El caso se listo con éxito', $data));
        try {
            $data = Caso::with('user', 'entidad', 'cTipoTarea.dTipoTarea')->where('id', $id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    public function bloqueoCaso(Request $request)
    {
        try {
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
            $data = Caso::with('user', 'entidad', 'resumen', 'tareas', 'actividad', 'Etiqueta', 'miembros', 'Galeria', 'Archivo')->where('id', $casoId)->first();
            broadcast(new TableroEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'El caso se actualizo con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $th->getMessage()));
        }
    }


    private function getCasoJoinTablero($casoId)
    {
        $data = DB::select('SELECT ca.*, ta.id as tablero_id FROM public.users us
        inner join crm.caso ca on ca.user_id = us.id
        INNER JOIN crm.fase fa on fa.id = ca.fas_id
        INNER JOIN crm.tablero ta on ta.id = fa.tab_id
        where ca.id = ' . $casoId);
        //echo('<-------------------------------->                 '.json_encode($data).'            <-------------------------------->');
        return $data[0];
    }

    public function listMiembrosCasoById($caso_id)
    {
        try {
            $miembros = Miembros::where('caso_id', $caso_id)->with('usuario')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $miembros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function editMiembrosCaso(Request $request, $caso_id)
    {
        try {
            $eliminados = $request->input('eliminados');
            $usuarios = $request->input('usuarios');
            $miembros = $request->all();

            //echo(json_encode($eliminados[0]['id']));
            $miembros = DB::transaction(function () use ($miembros, $caso_id, $eliminados, $usuarios, $request) {

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($caso_id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.miembros WHERE caso_id = " . $caso_id . " and user_id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($usuarios); $i++) {
                    $tabl = Miembros::where('caso_id', $caso_id)->where('user_id', $usuarios[$i])->first();
                    if (!$tabl) {
                        Miembros::create([
                            "caso_id" => $caso_id,
                            "user_id" => $usuarios[$i]['id'],
                            "chat_group_id" => $request->chat_group_id
                        ]);
                    }
                }
                return $miembros;
            });

            // $dataRe = Miembros::orderBy('id', 'DESC')->get();

            $dataRe = Caso::with('user', 'entidad', 'resumen', 'miembros.usuario.departamento', 'tareas')->where('id', $caso_id)->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editPrioridadCaso(Request $request, $caso_id)
    {
        try {
            $caso = $request->all();

            DB::transaction(function () use ($caso, $caso_id, $request) {

                $caso = Caso::findOrFail($caso_id);

                $caso->update([
                    "prioridad" => $request->prioridad,
                ]);
            });

            $data = Caso::with('user', 'entidad', 'resumen', 'miembros.usuario', 'tareas')->where('id', $caso_id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }


    public function editCasosUsuarioAsignado(Request $request, $caso_id)
    {

        try {

            DB::transaction(function () use ($caso_id, $request) {

                $caso = Caso::where('id', $caso_id)->first();

                $faseNuevaId = DB::select('SELECT f.id from crm.tablero t
                inner join crm.fase f on f.tab_id = t.id and f.tab_id = ? and f.fase_tipo = 1', [$request->tab_id])[0];
                //echo(json_encode($request->user_id));
                if ($faseNuevaId->id) {
                    $caso->user_id = $request->user_id;
                    $caso->user_anterior_id = $request->user_actual_id;
                    $caso->fas_id = $faseNuevaId->id;
                    $caso->fase_anterior_id = $request->fase_anterior_id;
                    $caso->save();
                    $miembro = new Miembros();
                    $miembro->user_id = $request->user_id;
                    $miembro->caso_id = $caso_id;
                    $miembro->save();
                }


            });

            $data = Caso::with('user', 'entidad', 'resumen', 'tareas', 'actividad', 'Etiqueta', 'miembros', 'Galeria', 'Archivo')->where('id', $caso_id)->first();
            broadcast(new TableroEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
