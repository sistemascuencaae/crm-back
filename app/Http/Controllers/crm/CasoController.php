<?php

namespace App\Http\Controllers\crm;

use App\Events\NotificacionesCrmEvent;
use App\Events\ReasignarCasoEvent;
use App\Events\TableroEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\ChatGroups;
use App\Models\crm\Audits;
use App\Models\crm\Caso;
use App\Models\crm\DTipoTarea;
use App\Models\crm\Miembros;
use App\Models\crm\Notificaciones;
use App\Models\crm\RequerimientoCaso;
use App\Models\crm\Tablero;
use App\Models\crm\Tareas;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
                $caso->estado_2 = 1;
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
                    $tarea->tab_id = $dtt->tab_id;
                    $tarea->marcado = false;
                    $caso->tareas()->save($tarea);
                }

                $newGrupo = new ChatGroups();
                $newGrupo->nombre = 'GRUPO CASO ' . $caso->id;
                $newGrupo->uniqd = 'caso.grupo.' . $caso->id;
                $newGrupo->save();

                $caso->nombre = 'CASO # ' . $caso->id;
                $caso->descripcion = 'CASO # ' . $caso->id;
                $caso->user_creador_id = $caso->user_id;

                $caso->save();
                for ($i = 0; $i < sizeof($miembros); $i++) {
                    $miembro = new Miembros();
                    $miembro->user_id = $miembros[$i];
                    $miembro->chat_group_id = $newGrupo->id;
                    $caso->miembros()->save($miembro);
                }

                /*---------******** ADD REQUERIMIENTOS AL CASO ********------------- */
                $reqFase = DB::select('SELECT rp.* from crm.requerimientos_predefinidos rp
                left join crm.requerimientos_caso rc on rc.caso_id = ? and rc.titulo = rp.nombre
                WHERE rc.titulo IS null and rp.fase_id = ?',
                    [$caso->id, $caso->fas_id]
                );
                for ($i = 0; $i < sizeof($reqFase); $i++) {
                    $reqCaso = new RequerimientoCaso();
                    $reqCaso->user_requiere_id = $caso->user_creador_id;
                    $reqCaso->form_control_name = Funciones::fun_obtenerAlfanumericos($reqFase[$i]->nombre);
                    $reqCaso->titulo = $reqFase[$i]->nombre;
                    $reqCaso->fas_id = $reqFase[$i]->fase_id;
                    $reqCaso->tab_id = $reqFase[$i]->tab_id;
                    $reqCaso->tipo_campo = $reqFase[$i]->tipo;
                    $reqCaso->caso_id = $caso->id;
                    $reqCaso->save();
                }
                return $this->getCaso($caso->id);
            });

            broadcast(new TableroEvent($casoCreado));


            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = Caso::class;
            $audit->auditable_id = $casoCreado->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($casoCreado); // json_encode para convertir en string ese array
            $audit->new_values = json_encode([]); // json_encode para convertir en string ese array
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addCaso';
            $audit->save();
            // END Auditoria


            return response()->json(RespuestaApi::returnResultado('success', 'Caso creado con exito.', $casoCreado));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear caso.', $th->getMessage()));
        }
    }

    public function list()
    {
        $data = Caso::with('caso.user', 'caso.entidad')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigio con exito', $data));
    }
    public function casoById($id)
    {
        $data = $this->getCaso($id);
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consiguió con éxito', $data));
    }
    public function editFase(Request $request)
    {
        $casoId = $request->input('casoId');
        $faseId = $request->input('faseId');
        $faseAnteriorId = $request->input('faseAnteriorId');


        try {
            $caso = Caso::find($casoId);
            $casoAudit = Caso::with(
                'user',
                'userCreador',
                'entidad',
                'fase.tablero',
            )->find($casoId); // Solo para el audits NADA MAS

            $audit = new Audits();
            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $casoAudit;
            $audit->old_values = json_encode($valorAntiguo); // json_encode para convertir en string ese array

            $caso->update([
                'fas_id' => $faseId,
                'fase_anterior_id' => $faseAnteriorId
            ]);


            $reqFase = DB::select(
                'SELECT rp.* from crm.requerimientos_predefinidos rp
                left join crm.requerimientos_caso rc on rc.caso_id = ? and rc.titulo = rp.nombre
                WHERE rc.titulo IS null and rp.fase_id = ?',
                [$casoId, $faseId]
            );
            for ($i = 0; $i < sizeof($reqFase); $i++) {
                $reqCaso = new RequerimientoCaso();
                $reqCaso->user_requiere_id = $caso->user_creador_id;
                $reqCaso->form_control_name = Funciones::fun_obtenerAlfanumericos($reqFase[$i]->nombre);
                $reqCaso->titulo = $reqFase[$i]->nombre;
                $reqCaso->fas_id = $reqFase[$i]->fase_id;
                $reqCaso->tab_id = $reqFase[$i]->tab_id;
                $reqCaso->tipo_campo = $reqFase[$i]->tipo;
                $reqCaso->caso_id = $caso->id;
                $reqCaso->valor_lista = $reqFase[$i]->valor_lista;
                $reqCaso->save();
            }

            $data = $this->getCaso($caso->id);
            broadcast(new TableroEvent($data));

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = Caso::class;
            $audit->auditable_id = $caso->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'editFase';
            // Establecer old_values y new_values
            $audit->new_values = json_encode($data); // json_encode para convertir en string ese array
            $audit->save();
            // END Auditoria

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

            $data = $this->getCaso($id);

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
            $bloqueado = $request->input("bloqueado");
            $bloqueado_user = $request->input("bloqueado_user");
            $caso = Caso::find($casoId);
            if ($caso) {
                $caso->bloqueado = $bloqueado;
                $caso->bloqueado_user = $bloqueado_user;
                $caso->save();
                $data = $this->getCasoJoinTablero($casoId);
            }
            $data = $this->getCaso($casoId);
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

            $dataRe = $this->getCaso($caso_id);
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

                // Obtener el old_values (valor antiguo)
                $valorAntiguo = $caso->prioridad;

                $caso->update([
                    "prioridad" => $request->prioridad,
                ]);

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'updated';
                $audit->auditable_type = Caso::class;
                $audit->auditable_id = $caso->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode(['prioridad' => $valorAntiguo]); // json_encode para convertir en string ese array
                $audit->new_values = json_encode(['prioridad' => $caso->prioridad]); // json_encode para convertir en string ese array
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'editPrioridad';
                $audit->save();
                // END Auditoria

            });

            $data = $this->getCaso($caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editarTipoCaso(Request $request, $caso_id)
    {
        try {
            $caso = $request->all();

            DB::transaction(function () use ($caso, $caso_id, $request) {
                $caso = Caso::findOrFail($caso_id);

                // Obtener el old_values (valor antiguo)
                $valorAntiguo = $caso->tc_id;

                $caso->update([
                    "tc_id" => $request->tc_id,
                ]);

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'updated';
                $audit->auditable_type = Caso::class;
                $audit->auditable_id = $caso->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode(['tc_id' => $valorAntiguo]); // json_encode para convertir en string ese array
                $audit->new_values = json_encode(['tc_id' => $caso->tc_id]); // json_encode para convertir en string ese array
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'editTipoCaso';
                $audit->save();
                // END Auditoria
            });

            $data = $this->getCaso($caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editObservacion(Request $request, $caso_id)
    {
        try {
            $caso = Caso::findOrFail($caso_id);

            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $caso->descripcion;

            DB::transaction(function () use ($caso, $request, $valorAntiguo) {
                $caso->update([
                    "descripcion" => $request->descripcion
                ]);

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'updated';
                $audit->auditable_type = Caso::class;
                $audit->auditable_id = $caso->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode(['descripcion' => $valorAntiguo]); // json_encode para convertir en string ese array
                $audit->new_values = json_encode(['descripcion' => $caso->descripcion]); // json_encode para convertir en string ese array
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'editDescripcion';
                $audit->save();
                // END Auditoria
            });

            $data = $this->getCaso($caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function reasignarCaso(Request $request)
    {
        $caso_id = $request->input('caso_id');
        // START Bloque de código que genera un registro de auditoría manualmente
        $audit = new Audits();
        try {
            $notificacion = DB::transaction(function () use ($request, $audit) {
                $caso_id = $request->input('caso_id');
                $estado_2 = $request->input('estado_2');
                $user_anterior_id = $request->input('user_anterior_id');
                $fase_anterior_id = $request->input('fase_anterior_id');
                $tablero_anterior_id = $request->input('tablero_anterior_id');
                $dep_anterior_id = $request->input('dep_anterior_id');
                $new_user_id = $request->input('new_user_id');
                $new_fase_id = $request->input('new_fase_id');
                $new_dep_id = $request->input('new_dep_id');
                $new_tablero_id = $request->input('new_tablero_id');

                //try {
                $casoEnProceso = Caso::find($caso_id);

                // Obtener el old_values (valor antiguo)
                $casoAudit = Caso::with(
                    'user',
                    'userCreador',
                    'entidad',
                    'fase.tablero',
                )->find($caso_id); // Solo para el audits NADA MAS

                $valorAntiguo = $casoAudit;
                $audit->old_values = json_encode($valorAntiguo); // json_encode para convertir en string ese array


                $casoEnProceso->fas_id = $new_fase_id;
                $casoEnProceso->user_id = $new_user_id;
                $casoEnProceso->estado_2 = $estado_2;
                $casoEnProceso->bloqueado = false;
                $casoEnProceso->bloqueado_user = '';
                $casoEnProceso->fase_anterior_id = $fase_anterior_id;
                $casoEnProceso->user_anterior_id = $user_anterior_id;
                $casoEnProceso->save();


                $audit->user_id = Auth::id();
                $audit->event = 'updated';
                $audit->auditable_type = Caso::class;
                $audit->auditable_id = $casoEnProceso->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'reasignarCaso';


                $miemExist = DB::select('SELECT * FROM crm.miembros where user_id = ? and caso_id = ?', [$new_user_id, $caso_id]);
                if (sizeof($miemExist) == 0) {
                    $miembro = new Miembros();
                    $miembro->user_id = $new_user_id;
                    $miembro->caso_id = $caso_id;
                    $miembro->save();
                }

                $noti = $this->getNotificacion(
                    'reasigno el caso #',
                    'Reasignar',
                    $casoEnProceso->user_anterior->name,
                    $casoEnProceso->id,
                    $casoEnProceso->user_id,
                    $casoEnProceso->fas_id,
                    $casoEnProceso->user->name
                );
                return $noti;
            });

            $data = $this->getCaso($caso_id);
            // END Auditoria
            if ($notificacion) {
                broadcast(new NotificacionesCrmEvent($notificacion));
            }

            broadcast(new ReasignarCasoEvent($data));

            $audit->new_values = json_encode($data); // json_encode para convertir en string ese array
            $audit->save();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }


        // echo json_encode($caso_id);
        // echo json_encode($user_anterior_id);
        // echo json_encode($fase_anterior_id);
        // echo json_encode($tablero_anterior_id);
        // echo json_encode($dep_anterior_id);
        // echo json_encode($new_user_id);
        // echo json_encode($new_fase_id);
        // echo json_encode($new_dep_id);
        // echo json_encode($new_tablero_id);






        // try {
        //     $notificacion = DB::transaction(function () use ($caso_id, $request) {

        //         $caso = Caso::where('id', $caso_id)->first();

        //         // $faseNuevaId = DB::select('SELECT f.id from crm.tablero t
        //         // inner join crm.fase f on f.tab_id = t.id and f.tab_id = ? and f.fase_tipo = 1', [$request->tab_id])[0];
        //         //echo(json_encode($request->user_id));
        //         if ($faseNuevaId->id) {
        //             $caso->fas_id = $faseNuevaId->id;
        //             $caso->estado_2 = $request->estado_2;
        //             $caso->bloqueado = false;
        //             $caso->bloqueado_user = '';
        //             $caso->user_id = $request->user_id;
        //             $caso->user_anterior_id = $request->user_actual_id;
        //             $caso->fase_anterior_id = $request->fase_anterior_id;
        //             $caso->save();
        //             $meimbroExiste = DB::select('SELECT * FROM crm.miembros where user_id = ? and caso_id = ?', [$request->user_actual_id, $caso_id]);
        //             if (sizeof($meimbroExiste) == 0) {
        //                 $miembro = new Miembros();
        //                 $miembro->user_id = $request->user_actual_id;
        //                 $miembro->caso_id = $caso_id;
        //                 $miembro->save();
        //             }
        //         }

        //         $noti = $this->getNotificacion(
        //             'reasigno el caso #',
        //             'Reasignar',
        //             $caso->user_anterior->name,
        //             $caso->id,
        //             $caso->user_id,
        //             $caso->fas_id,
        //             $caso->user->name
        //         );

        //         return $noti;
        //     });

        //     $data = $this->getCaso($caso_id);
        //     if ($notificacion) {
        //         broadcast(new NotificacionesCrmEvent($notificacion));
        //     }

        //     broadcast(new ReasignarCasoEvent($data));
        //     return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        // }
    }

    public function depUserTablero($casoId)
    {
        try {

            $usuarios = DB::select("SELECT * from public.users where estado  = true");
            //$tableros = DB::select("SELECT * from crm.tablero where estado = true");
            $tableros = Tablero::with('tableroUsuario.usuario')->where('estado',true)->get();
            $departamentos = DB::select("SELECT * from crm.departamento where estado = true");
            $fases = DB::select("SELECT * from crm.fase where estado = true");
            $depUserTablero = DB::select(
                'SELECT
            d.id as dep_anterior_id,
            t.id as tablero_anterior_id,
            c.fase_anterior_id,
            c.user_anterior_id,
            c.estado_2,
            c.fase_creacion_id,
            c.dep_creacion_id,
            c.tablero_creacion_id,
            c.user_creador_id,
            usant.usu_tipo
            from crm.caso c
            inner join crm.fase f on f.id = c.fase_anterior_id
            inner join crm.tablero t on t.id = f.tab_id
            inner join crm.departamento d on d.id = t.dep_id
            inner join public.users us on us.id = c.user_creador_id
            inner join public.users usant on usant.id = c.user_anterior_id
            where c.id = ? limit 1;',
                [$casoId]
            );
            $data = (object) [
                "usuarios" => $usuarios,
                "departamentos" => $departamentos,
                "tableros" => $tableros,
                "fases" => $fases,
                "depUserTablero" => null
            ];

            if ($depUserTablero) {
                $data->depUserTablero = $depUserTablero[0];
            }
            return response()->json(RespuestaApi::returnResultado('success', 'Exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $th->getMessage()));
        }
    }

    public function getCaso($casoId)
    {


        $tabId = DB::select('SELECT t.id FROM crm.caso co
            inner join crm.fase fa on fa.id = co.fas_id
            inner join crm.tablero t on t.id = fa.tab_id
        where co.id = ' . $casoId)[0];



        return Caso::with([
            'user',
            'userCreador',
            'entidad',
            'resumen',
            'tareas' => function ($query) use ($tabId) {
                $query->where('tab_id', $tabId->id);
            },
            'actividad',
            'Etiqueta',
            'miembros.usuario.departamento',
            'Galeria',
            'Archivo',
            'req_caso',

            'tablero',
            'fase.tablero',

        ])->where('id', $casoId)->first();
    }

    public function getNotificacion($descripcion, $tipo, $usuarioAccion, $casoId, $userId, $faseId, $user_name_actual)
    {
        try {
            $tabDepa = DB::select('SELECT t.id as tab_id, d.id as dep_id FROM crm.tablero t inner join crm.fase f on f.tab_id = t.id
            inner join crm.departamento d on d.id = t.dep_id
            where f.id = ? limit 1;', [$faseId]);

            $noti = Notificaciones::create([
                "titulo" => 'CRM NOTIFICACION',
                "descripcion" => $descripcion,
                "estado" => true,
                "color" => '#5DADE2',
                "caso_id" => $casoId,
                "dep_id" => sizeof($tabDepa) > 0 ? $tabDepa[0]->dep_id : null,
                "tipo" => $tipo,
                "usuario_accion" => $usuarioAccion,
                "usuario_destino_id" => $userId,
                "tab_id" => sizeof($tabDepa) > 0 ? $tabDepa[0]->tab_id : null,
            ]);

            $data = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.entidad', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'tablero', 'user_destino')
                ->where('id', $noti->id)
                ->orderBy('id', 'DESC')->first();

            //     return $data;
            // } catch (\Throwable $th) {
            //     return null;
            // }
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}