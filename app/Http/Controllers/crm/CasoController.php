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
use App\Models\crm\ClienteCrm;
use App\Models\crm\ControlTiemposCaso;
use App\Models\crm\credito\ClienteEnrolamiento;
use App\Models\crm\DTipoTarea;
use App\Models\crm\Estados;
use App\Models\crm\EstadosFormulas;
use App\Models\crm\Miembros;
use App\Models\crm\Notificaciones;
use App\Models\crm\ReferenciasCliente;
use App\Models\crm\RequerimientoCaso;
use App\Models\crm\Tablero;
use App\Models\crm\Tareas;
use App\Models\crm\TelefonosCliente;
use App\Models\crm\TelefonosReferencias;
use App\Models\crm\TipoCaso;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CasoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function add(Request $request)
    {
        $casoInput = $request->all();
        $miembros = $request->input('miembros');
        try {
            $casoCreado = DB::transaction(function () use ($casoInput, $miembros) {
                $userLoginId = auth('api')->user()->id;
                $caso = new Caso($casoInput);
                //$caso->estado_2 = 1;
                $caso->save();

                //buscar las tareas predefinidas
                //$arrayDtipoTareas = DTipoTarea::where('ctt_id', $caso->ctt_id)->get();
                $arrayDtipoTareas = DB::select('SELECT dt.* from crm.tipo_caso tc
                inner join crm.ctipo_tarea ct on ct.id = tc.ctt_id
                inner join crm.dtipo_tarea dt on dt.ctt_id = ct.id
                where tc.id = ?', [$caso->tc_id]);
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
                $estadoInicial = Estados::where('tab_id', $caso->tablero_creacion_id)->where('tipo_estado_id', 1)->first();
                //--------------------
                $caso->estado_2 = $estadoInicial->id;
                $caso->nombre = 'CASO # ' . $caso->id;
                $caso->user_creador_id = $userLoginId;
                $caso->cliente_id = $this->validarClienteSolicitudCredito($caso->ent_id)->id;
                $caso->save();
                for ($i = 0; $i < sizeof($miembros); $i++) {
                    $miembro = new Miembros();
                    $miembro->user_id = $miembros[$i];
                    $miembro->chat_group_id = $newGrupo->id;
                    $caso->miembros()->save($miembro);
                }

                $this->addRequerimientosFase($caso->id, $caso->fas_id, $caso->user_creador_id);
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
        $data = Caso::with('caso.user', 'caso.clienteCrm')->get();
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
                'clienteCrm',
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

            // start diferencia de tiempos en horas minutos y segundos
            $tipo = 3;
            $this->calcularTiemposCaso(
                $caso,
                $caso->id,
                $caso->estado_2,
                $caso->fas_id,
                $tipo,
                $caso->user_id
            );
            // end diferencia de tiempos en horas minutos y segundos

            $this->addRequerimientosFase($caso->id, $caso->fas_id, $caso->user_creador_id);
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
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e));
        }
    }

    public function listCasoById($id)
    {
        // $data = Caso::with('user', 'clienteCrm', 'cTipoTarea.dTipoTarea')->where('id', $id)->get();
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
                $casoAudit = TipoCaso::find($caso->tc_id); // Solo para el audits NADA MAS

                // Obtener el old_values (valor antiguo)
                $valorAntiguo = $casoAudit;

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
                $audit->old_values = json_encode($valorAntiguo); // json_encode para convertir en string ese array

                // Obtener el old_values (valor antiguo)
                $casoAuditNewValue = TipoCaso::find($caso->tc_id); // Solo para el audits NADA MAS

                $audit->new_values = json_encode($casoAuditNewValue); // json_encode para convertir en string ese array
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
        try {
            $notificacion = DB::transaction(function () use ($request) {
                $caso_id = $request->input('caso_id');
                $estado_2 = $request->input('estado_2');
                $user_anterior_id = $request->input('user_anterior_id');
                $fase_anterior_id = $request->input('fase_anterior_id');
                $fase_anterior_id_reasigna = $request->input('fase_anterior_id_reasigna');
                $tablero_anterior_id = $request->input('tablero_anterior_id');
                $dep_anterior_id = $request->input('dep_anterior_id');
                $new_user_id = $request->input('new_user_id');
                $new_fase_id = $request->input('new_fase_id');
                $new_dep_id = $request->input('new_dep_id');
                $new_tablero_id = $request->input('new_tablero_id');

                //try {
                $casoEnProceso = Caso::find($caso_id);
                $casoEnProceso->fas_id = $new_fase_id;
                $casoEnProceso->user_id = $new_user_id;
                $casoEnProceso->estado_2 = $estado_2;
                $casoEnProceso->bloqueado = false;
                $casoEnProceso->bloqueado_user = '';
                $casoEnProceso->fase_anterior_id_reasigna = $fase_anterior_id_reasigna;
                $casoEnProceso->fase_anterior_id = $fase_anterior_id;
                $casoEnProceso->user_anterior_id = $user_anterior_id;
                $casoEnProceso->save();

                // start diferencia de tiempos en horas minutos y segundos
                $tipo = 1;
                $this->calcularTiemposCaso(
                    $casoEnProceso,
                    $casoEnProceso->id,
                    $casoEnProceso->estado_2,
                    $casoEnProceso->fas_id,
                    $tipo,
                    $casoEnProceso->user_id
                );
                // end diferencia de tiempos en horas minutos y segundos

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
            if ($notificacion) {
                broadcast(new NotificacionesCrmEvent($notificacion));
            }

            broadcast(new ReasignarCasoEvent($data));
            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se actualizo con éxito',
                    $data
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function respuestaCaso(Request $request)
    {
        try {

            $estadoFormId = $request->input('estadoFormId');
            $casoId = $request->input('casoId');


            $formula = EstadosFormulas::find($estadoFormId);
            if (!$formula) {
                return response()->json(RespuestaApi::returnResultado('error', 'Error', 'La formula no existe.'));
            }
            $casoEnProceso = Caso::find($casoId);
            if (!$casoEnProceso) {
                return response()->json(RespuestaApi::returnResultado('error', 'Error', 'El caso no existe.'));
            }
            $casoEnProceso->fas_id = $formula->fase_id;
            $casoEnProceso->estado_2 = $formula->est_id_proximo;
            $casoEnProceso->save();
            $data = $this->getCaso($casoEnProceso->id);
            broadcast(new ReasignarCasoEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function depUserTablero($casoId)
    {
        try {
            $tableros = Tablero::with('tableroUsuario.usuario')->where('estado', true)->get();
            $departamentos = DB::select("SELECT * from crm.departamento where estado = true");
            $fases = DB::select("SELECT * from crm.fase where estado = true");
            $estados = Estados::all();
            $depUserTablero = DB::select(
                'SELECT
            d.id as dep_anterior_id,
            t.id as tablero_anterior_id,
            c.fase_anterior_id,
            c.fase_anterior_id_reasigna,
            c.user_anterior_id,
            c.estado_2,
            c.fase_creacion_id,
            c.dep_creacion_id,
            c.tablero_creacion_id,
            c.user_creador_id,
            usant.usu_tipo
            from crm.caso c
            inner join crm.fase f on f.id = c.fase_anterior_id_reasigna
            inner join crm.tablero t on t.id = f.tab_id
            inner join crm.departamento d on d.id = t.dep_id
            inner join public.users us on us.id = c.user_creador_id
            inner join public.users usant on usant.id = c.user_anterior_id
            where c.id = ? limit 1;',
                [$casoId]
            );
            $data = (object) [
                "departamentos" => $departamentos,
                "tableros" => $tableros,
                "fases" => $fases,
                "estados" => $estados,
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
            'clienteCrm',
            'resumen',
            'tareas' => function ($query) use ($tabId) {
                $query->where('tab_id', $tabId->id);
            },
            'actividad',
            'Etiqueta',
            'miembros.usuario.departamento',
            'Galeria',
            'Archivo',
            'req_caso' => function ($query) {
                $query->orderBy('id', 'asc')->orderBy('orden', 'asc');
            },
            'tablero',
            'fase.tablero',
            'estadodos'

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

            $data = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'tablero', 'user_destino')
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

    public function testControl($casoId, $faseId, $userCreadorId)
    {
        $reqFase = DB::select(
            'SELECT rp.* from crm.Dequerimientos_predefinidos rp
                left join crm.requerimientos_caso rc on rc.caso_id = ? and rc.titulo = rp.nombre
                WHERE rc.titulo IS null and rp.fase_id = ?',
            [$casoId, $faseId]
        );
        $arrayTest = [];
        for ($i = 0; $i < sizeof($reqFase); $i++) {
            $reqCaso = new RequerimientoCaso();
            $reqCaso->form_control_name = Funciones::fun_obtenerAlfanumericos($reqFase[$i]->nombre);
            $reqCaso->user_requiere_id = $userCreadorId;
            $reqCaso->titulo = $reqFase[$i]->nombre;
            $reqCaso->fas_id = $reqFase[$i]->fase_id;
            $reqCaso->tab_id = $reqFase[$i]->tab_id;
            $reqCaso->tipo_campo = $reqFase[$i]->tipo;
            $reqCaso->caso_id = $casoId;
            $reqCaso->valor_lista = $reqFase[$i]->valor_lista;
            $reqCaso->requerido = $reqFase[$i]->requerido;

            if ($reqCaso->tipo_campo == 'lista') {
                $array = explode(',', $reqCaso->valor_lista);
                $nuevoArray = array();

                foreach ($array as $item) {
                    $objeto = array(
                        'id' => $item,
                        'valor' => $item
                    );
                    $nuevoArray[] = $objeto;
                }

                $reqCaso->valor_multiple = $nuevoArray;
            }
            array_push($arrayTest, $reqCaso);
            //echo ('$reqCaso: '.json_encode($reqCaso));
            //$reqCaso->save();
        }

        return response()->json($arrayTest);
    }

    public function addRequerimientosFase($casoId, $faseId, $userCreadorId)
    {

        /*---------******** ADD REQUERIMIENTOS AL CASO ********------------- */


        $casorReqEquifa = DB::select("SELECT * from crm.requerimientos_caso rc where rc.caso_id = ? and tipo_campo = ?; ", [$casoId,'equifax']);

        if($casorReqEquifa){
            //hacer esto si ya existe un requerimiento equifax
            $reqFase = DB::select(
                "SELECT rp.* from crm.requerimientos_predefinidos rp
                left join crm.requerimientos_caso rc on rc.caso_id = ? and rc.titulo = rp.nombre
                WHERE rc.titulo IS null and rp.fase_id = ?  and rp.tipo <> 'equifax' order by rp.orden asc",
                [$casoId, $faseId]
            );
        }else{
            //hacer esto si todavia no tiene requerimiento equifax
            $reqFase = DB::select(
                'SELECT rp.* from crm.requerimientos_predefinidos rp
                left join crm.requerimientos_caso rc on rc.caso_id = ? and rc.titulo = rp.nombre
                WHERE rc.titulo IS null and rp.fase_id = ? order by rp.orden asc',
                [$casoId, $faseId]
            );
        }






        for ($i = 0; $i < sizeof($reqFase); $i++) {
            $reqCaso = new RequerimientoCaso();
            $reqCaso->form_control_name = Funciones::fun_obtenerAlfanumericos($reqFase[$i]->nombre);
            $reqCaso->user_requiere_id = $userCreadorId;
            $reqCaso->titulo = $reqFase[$i]->nombre;
            $reqCaso->fas_id = $reqFase[$i]->fase_id;
            $reqCaso->tab_id = $reqFase[$i]->tab_id;
            $reqCaso->tipo_campo = $reqFase[$i]->tipo;
            $reqCaso->caso_id = $casoId;
            $reqCaso->requerido = $reqFase[$i]->requerido;
            $reqCaso->valor_lista = $reqFase[$i]->valor_lista;
            $reqCaso->orden = $reqFase[$i]->orden;
            $reqCaso->acc_publico = $reqFase[$i]->acc_publico;
            if ($reqCaso->tipo_campo == 'lista') {
                $array = explode(',', $reqCaso->valor_lista);
                $nuevoArray = array();

                foreach ($array as $item) {
                    $objeto = array(
                        'id' => $item,
                        'valor' => $item
                    );
                    $nuevoArray[] = $objeto;
                }

                $reqCaso->valor_multiple = json_encode($nuevoArray);
            }
            $reqCaso->marcado = $this->validarEnrolamiento($casoId, $reqFase[$i]->tipo);
            $reqCaso->save();
        }
    }

    public function validarEnrolamiento($casoId, $tipoCampo)
    {

        $clienteCaso = Caso::find($casoId);
        if ($tipoCampo !== 'equifax') {
            return false;
        }
        if ($clienteCaso == null) {
            return false;
        }
        $cedula = $clienteCaso['clienteCrm']->ent_identificacion;
        if ($cedula == '1234567991') {
            return false;
        }
        //--El cliente ya esta enrolado?
        $clienteEnrolamiento = ClienteEnrolamiento::where('IdNumber', $cedula)->first();
        if ($clienteEnrolamiento) {
            return true;
        }

        return false;
    }

    public function validarClienteSolicitudCredito($entId)
    {
        try {
            $data = DB::transaction(function () use ($entId) {

                //$cliente = DB::selectOne('SELECT * FROM crm.cliente WHERE ent_id = ?', [$entId]);
                $cliente = ClienteCrm::where('ent_id', $entId)->first();
                if ($cliente)
                    return $cliente;
                //entidad dinamo
                $entidadPublic = DB::selectOne('SELECT * FROM public.entidad WHERE ent_id = ?', [$entId]);
                //telefonos del cliente en el dinamo
                $telefonosCliDynamo = DB::selectOne("SELECT com.com_telefono1 as tel_1_trabajo_sc, com.com_telefono2 as tel_2,
	            (SELECT te.tel_numero
                FROM telefono te
                WHERE te.tel_id = ent.ent_telefono_principal) AS tel_domicilio_sc,
                ( SELECT array_to_string(array_agg(tel.tel_numero), ','::text) AS array_to_string
                FROM telefono_entidad te
                JOIN telefono tel ON te.tel_id = tel.tel_id
                WHERE te.ent_id = ent.ent_id) AS telefonos_adicionales
                FROM entidad ent
                inner join public.telefono telp on telp.tel_id = ent.ent_telefono_principal
                LEFT JOIN public.cliente cli ON cli.ent_id = ent.ent_id
                LEFT JOIN public.cliente_anexo cliane ON cliane.cli_id = cli.cli_id
                LEFT JOIN public.compania com ON cliane.com_id = com.com_id
                where ent.ent_identificacion = ?", [$entidadPublic->ent_identificacion]);
                //vista solicitud de credito datos del cliente
                $clienteSC = DB::selectOne("SELECT * FROM crm.av_solicitud_credito WHERE ent_id = ?", [$entId]);
                //cliente conyuge
                $clienteConyuge = DB::selectOne(" SELECT 'CED'::text AS tipodocumento,
                    cliane.cliane_identificacion_conyuge AS numerodocumento,
                    split_part(btrim(cliane.cliane_nombre_conyuge::text), ' '::text, 1) AS apellidopaterno,
                    split_part(btrim(cliane.cliane_nombre_conyuge::text), ' '::text, 2) AS apellidomaterno,
                    split_part(btrim(cliane.cliane_nombre_conyuge::text), ' '::text, 3) AS primernombre,
                    cliane.cliane_fecha_nacimiento_conyuge AS fecha_nacimiento,
	                    CASE
                        WHEN cliane.cliane_sexo_conyuge::text = 4::text THEN 'M'::text
                        WHEN cliane.cliane_sexo_conyuge::text = 5::text THEN 'F'::text
                        ELSE 'F'::text
                    END AS sexo
                    FROM entidad ent
                    LEFT JOIN cliente cli ON cli.ent_id = ent.ent_id
                    LEFT JOIN cliente_anexo cliane ON cliane.cli_id = cli.cli_id
                    where ent.ent_id = ? and cliane.cliane_identificacion_conyuge <> null", [$entId]);
                //crear nuevo cliente
                $nuevoCliente = new ClienteCrm();
                $nuevoCliente->ent_id = $entidadPublic->ent_id;
                $nuevoCliente->tipo_identificacion = $entidadPublic->ent_tipo_identificacion;
                $nuevoCliente->identificacion = $entidadPublic->ent_identificacion;
                $nuevoCliente->nombres = $entidadPublic->ent_nombres;
                $nuevoCliente->apellidos = $entidadPublic->ent_nombres;
                $nuevoCliente->fechanacimiento = $entidadPublic->ent_fechanacimiento;
                $nuevoCliente->email = $entidadPublic->ent_email;
                if ($clienteSC) {
                    $nuevoCliente->pai_nombre = $clienteSC->pai_nombre;
                    $nuevoCliente->ctn_nombre = $clienteSC->ctn_nombre;
                    $nuevoCliente->prq_nombre = $clienteSC->prq_nombre;
                    $nuevoCliente->prv_nombre = $clienteSC->prv_nombre;
                    $nuevoCliente->nivel_educacion = $clienteSC->nivel_educacion;
                    $nuevoCliente->cactividad_economica = $clienteSC->cactividad_economica;
                    $nuevoCliente->numero_dependientes = $clienteSC->numero_dependientes;
                    $nuevoCliente->nombre_empresa = $clienteSC->nombre_empresa;
                    $nuevoCliente->tipo_empresa = $clienteSC->tipo_empresa;
                    $nuevoCliente->direccion = $clienteSC->direccion;
                    $nuevoCliente->numero_casa = $clienteSC->numero_casa;
                    $nuevoCliente->calle_secundaria = $clienteSC->calle_secundaria;
                    $nuevoCliente->referencias_direccion = $clienteSC->referencias_direccion;
                    $nuevoCliente->trabajo_direccion = $clienteSC->trabajo_direccion;
                    $nuevoCliente->fecha_ingreso = $clienteSC->fecha_ingreso;
                    $nuevoCliente->ingresos_totales = $clienteSC->ingresos_totales;
                    $nuevoCliente->gastos_totales = $clienteSC->gastos_totales;
                    $nuevoCliente->activos_totales = $clienteSC->activos_totales;
                    $nuevoCliente->pasivos_totales = $clienteSC->pasivos_totales;
                }
                if ($clienteConyuge) {
                    $nuevoCliente->cedula_conyuge = $clienteConyuge->numerodocumento;
                    $nuevoCliente->nombres_conyuge = $clienteConyuge->primernombre;
                    $nuevoCliente->apellidos_conyuge = $clienteConyuge->apellidopaterno . ' ' . $clienteConyuge->apellidomaterno;
                    $nuevoCliente->sexo_conyuge = $clienteConyuge->sexo;
                    $nuevoCliente->fecha_nacimiento_conyuge = $clienteConyuge->fecha_nacimiento;
                }
                $nuevoCliente->save();


                if ($telefonosCliDynamo != null) {
                    $telefonoCliente = new TelefonosCliente();
                    $telefonoCliente->cli_id = $nuevoCliente->id;
                    $telefonoCliente->numero_telefono = $telefonosCliDynamo->tel_1_trabajo_sc;
                    $telefonoCliente->tipo_telefono = "No Definido";
                    $telefonoCliente->save();
                    $telefonoCliente = new TelefonosCliente();
                    $telefonoCliente->cli_id = $nuevoCliente->id;
                    $telefonoCliente->numero_telefono = $telefonosCliDynamo->tel_2;
                    $telefonoCliente->tipo_telefono = "No Definido";
                    $telefonoCliente->save();
                    $telefonoCliente = new TelefonosCliente();
                    $telefonoCliente->cli_id = $nuevoCliente->id;
                    $telefonoCliente->numero_telefono = $telefonosCliDynamo->tel_domicilio_sc;
                    $telefonoCliente->tipo_telefono = "No Definido";
                    $telefonoCliente->save();
                    $telefonosAdicionales = $telefonosCliDynamo->telefonos_adicionales;
                    $telefonosAdicionalesArray = explode(',', $telefonosAdicionales);
                    foreach ($telefonosAdicionalesArray as $telefono) {
                        $telefonoCliente = new TelefonosCliente();
                        $telefonoCliente->cli_id = $nuevoCliente->id;
                        $telefonoCliente->numero_telefono = $telefono;
                        $telefonoCliente->tipo_telefono = "No Definido";
                        $telefonoCliente->save();
                    }
                }




                $clienteReferencias = DB::select("SELECT
		    split_part(btrim(refane.refane_nombre::text), ' '::text, 2) AS refane2_apellpa,
			CASE
				WHEN length(split_part(btrim(refane.refane_nombre::text), ' '::text, 3)) > 0 THEN split_part(btrim(refane.refane_nombre::text), ' '::text, 3)
				ELSE 'SN'::text
			END AS refane2_apellma,
		    split_part(btrim(refane.refane_nombre::text), ' '::text, 1) AS refane2_nombre,
		    refane.refane_nombre,
            ( SELECT fa_datos_cliente('accion'::character varying, refane.refane_descripcion) AS fa_datos_cliente) AS refane_parentesco,
            refane.refane_direccion,
            refane.refane_numero_telefono,
            refane.refane_numero_telefono2,
            refane.refane_numero_telefono3
            from public.entidad ent
            inner join public.referencias_anexo refane on refane.ent_id = ent.ent_id
            where ent.ent_id = ? ", [$entId]);
                if ($clienteReferencias) {
                    foreach ($clienteReferencias as $ref) {
                        $nuevaRef = new ReferenciasCliente();
                        $nuevaRef->cli_id = $nuevoCliente->id;
                        $nuevaRef->ent_id = $nuevoCliente->ent_id;
                        $nuevaRef->nombre1 = $ref->refane2_nombre;
                        $nuevaRef->apellido1 = $ref->refane2_apellpa;
                        $nuevaRef->apellido2 = $ref->refane2_apellma;
                        $nuevaRef->nombre_comercial = $ref->refane_nombre;
                        $nuevaRef->parentesco = $ref->refane_parentesco;
                        $nuevaRef->direccion = $ref->refane_direccion;
                        $nuevaRef->estado = true;
                        $nuevaRef->save();
                        //telefono 1
                        if ($ref->refane_numero_telefono) {
                            $telefonoRef = new TelefonosReferencias();
                            $telefonoRef->ref_id = $nuevaRef->id;
                            $telefonoRef->numero_telefono = $ref->refane_numero_telefono;
                            $telefonoRef->tipo_telefono = "No Definido";
                            $telefonoRef->save();
                        }
                        //telefono 2
                        if ($ref->refane_numero_telefono2) {
                            $telefonoRef = new TelefonosReferencias();
                            $telefonoRef->ref_id = $nuevaRef->id;
                            $telefonoRef->numero_telefono = $ref->refane_numero_telefono2;
                            $telefonoRef->tipo_telefono = "No Definido";
                            $telefonoRef->save();
                        }
                        //telefono 3
                        if ($ref->refane_numero_telefono3) {
                            $telefonoRef = new TelefonosReferencias();
                            $telefonoRef->ref_id = $nuevaRef->id;
                            $telefonoRef->numero_telefono = $ref->refane_numero_telefono3;
                            $telefonoRef->tipo_telefono = "No Definido";
                            $telefonoCliente->save();
                        }
                    }
                }

                $resul = ClienteCrm::with('telefonos', 'referencias.telefonos')->find($nuevoCliente->id);
                return $resul;
            });

            return $data;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function calcularTiemposCaso($caso, $caso_id, $estado_2, $fase_id, $tipo, $user_id)
    {
        // Tipo
        // 1 reasignacion manual
        // 2 automatica por formulas
        // 3 cambio de fase           

        try {
            DB::transaction(function () use ($caso, $caso_id, $estado_2, $fase_id, $tipo, $user_id) {

                // Consulta si ya existe un registro con el mismo caso_id
                $registroAnterior = ControlTiemposCaso::where('caso_id', $caso_id)->latest()->first();

                if ($registroAnterior) {

                    // Crear un nuevo registro en ControlTiemposCaso
                    $nuevoRegistro = ControlTiemposCaso::create([
                        "caso_id" => $caso_id,
                        "est_caso_id" => $estado_2,
                        "tiempo_cambio" => null,
                        "fase_id" => $fase_id,
                        "tipo" => $tipo,
                        "user_id" => $user_id,
                    ]);

                    // Consulta si ya existe un registro anterior con el mismo caso_id
                    $registroAnterior = ControlTiemposCaso::where('caso_id', $caso_id)
                        ->where('id', '<', $nuevoRegistro->id)
                        ->latest()
                        ->first();

                    if ($registroAnterior) {
                        // Convierte las fechas a objetos Carbon para manejar la zona horaria
                        $created_at_actual = Carbon::parse($nuevoRegistro->created_at);
                        $created_at_anterior = Carbon::parse($registroAnterior->created_at);

                        // Calcula la diferencia de tiempo en segundos
                        $diferenciaSegundos = $created_at_actual->diffInSeconds($created_at_anterior);

                        // Calcula las horas, minutos y segundos
                        $horas = floor($diferenciaSegundos / 3600);
                        $diferenciaSegundos %= 3600;
                        $minutos = floor($diferenciaSegundos / 60);
                        $segundos = $diferenciaSegundos % 60;

                        // Formatea la diferencia de tiempo en formato TIME (HH:MM:SS)
                        $tiempoCambio = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);

                        // Actualiza el nuevo registro con el tiempo_cambio calculado
                        $nuevoRegistro->update([
                            "tiempo_cambio" => $tiempoCambio,
                        ]);
                    } else {
                        // Si no hay registro anterior, el tiempo_cambio se establece como null
                        $nuevoRegistro->update([
                            "tiempo_cambio" => null,
                        ]);
                    }

                } else {

                    // Crea el nuevo registro con tiempo_cambio como null
                    $primerRegistro = ControlTiemposCaso::create([
                        "caso_id" => $caso->id,
                        "est_caso_id" => $caso->estado_2,
                        "tiempo_cambio" => null,
                        "fase_id" => $caso->fas_id,
                        "tipo" => $tipo,
                        "user_id" => $caso->user_id,
                    ]);

                    $created_at_actual = Carbon::parse($primerRegistro->created_at);
                    $created_at_anterior = Carbon::parse($caso->created_at);

                    // Calcula la diferencia de tiempo en segundos
                    $diferenciaSegundos = $created_at_actual->diffInSeconds($created_at_anterior);

                    // Calcula las horas, minutos y segundos
                    $horas = floor($diferenciaSegundos / 3600);
                    $diferenciaSegundos %= 3600;
                    $minutos = floor($diferenciaSegundos / 60);
                    $segundos = $diferenciaSegundos % 60;

                    // Formatea la diferencia de tiempo en formato TIME (HH:MM:SS)
                    $tiempoCambio = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);

                    // Crea el nuevo registro con el tiempo_cambio calculado
                    ControlTiemposCaso::create([
                        "caso_id" => $caso->id,
                        "est_caso_id" => $caso->estado_2,
                        "tiempo_cambio" => $tiempoCambio,
                        "fase_id" => $caso->fas_id,
                        "tipo" => $tipo,
                        "user_id" => $caso->user_id,
                    ]);

                }

            });
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}