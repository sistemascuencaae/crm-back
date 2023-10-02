<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CondicionesFaseMover;
use App\Models\crm\Estados;
use App\Models\crm\Tablero;
use App\Models\crm\TableroUsuario;
use App\Models\crm\VistaMisCasos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableroController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAllTablerosWithFases()
    {
        try {
            $tableros = Tablero::where('estado', true)->with('fase', 'estados')->orderBy('id', 'desc')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listByTablerosIdWithFases($tab_id)
    {
        try {
            $tablero = Tablero::where('id', $tab_id)->where('estado', true)->with('fase.respuestas')->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tablero));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    //LISTA DE TODOS LOS TABLEROS
    public function listAll()
    {
        try {
            $tableros = Tablero::with('tableroUsuario')->orderBy('id', 'desc')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // start para superUsuario
    public function listAllTablerosActivos()
    {
        try {
            $tableros = Tablero::with('tableroUsuario.usuario.departamento')->where('estado', true)->orderBy("id", "desc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAllTablerosInactivos()
    {
        try {
            $tableros = Tablero::with('tableroUsuario.usuario.departamento')->where('estado', false)->orderBy("id", "desc")->get();

            // return response()->json([
            //     "tableros" => $tableros,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    // end para superUsuario

    public function listTableroByUser($user_id)
    {
        try {
            // $tableros = Tablero::where("tableroUsuario", $user_id)->with('tableroUsuario.usuario.departamento')->where('estado', true)->orderBy("id", "desc")->get();
            $tableros = Tablero::whereHas('tableroUsuario', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })->with('tableroUsuario.usuario.departamento')->where('estado', true)->orderBy('id', 'desc')->get();
            // return response()->json([
            //     "tableros" => $tableros,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addTablero(Request $request)
    {
        try {
            $tab = $request->all();
            $t = DB::transaction(function () use ($tab) {
                $tablero = Tablero::create($tab);
                for ($i = 0; $i < sizeof($tab['usuarios']); $i++) {
                    DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$tab['usuarios'][$i]['id'], $tablero['id']]);
                }


                $condicion = CondicionesFaseMover::create([
                    "parametro" => '[]',
                ]);

                $estadoCasoInicial = Estados::create([
                    "nombre" => 'PENDIENTE',
                    "estado" => true,
                    "tab_id" => $tablero->id,
                    "tipo_estado_id" => 1
                ]);

                DB::insert("INSERT INTO crm.fase
                (tab_id, nombre, descripcion, estado, orden, created_at, updated_at, generar_caso, color_id, fase_tipo, cnd_mover_id)
                VALUES(?, 'BANDEJA DE ENTRADA', 'SE CARGARAN TODOS LOS CASOS SIN ASIGNAR', true, 1, ?, ?, true, 22, 1, ?);", [$tablero->id, $tablero->created_at, $tablero->updated_at, $condicion->id]);

                DB::insert("INSERT INTO public.users
                (name, estado, surname, usu_alias, email,
                password, created_at, updated_at, phone, fecha_nacimiento,
                address, usu_tipo_analista, dep_id, usu_tipo, tab_id, en_linea)
                VALUES('USUARIO GENERAL {$tablero->nombre} {$tablero->id}', true, 'USUARIO GENERAL {$tablero->nombre} {$tablero->id}', 'USUARIOGENERAL{$tablero->id}', 'usuariogeneral{$tablero->id}@gmail.com',
                '123456', '{$tablero->created_at}', '{$tablero->updated_at}', '9999999999', '{$tablero->created_at}',
                'USUARIO GENERAL', NULL, $tablero->dep_id, 1, $tablero->id,true);");


                // Insert de resultados de la Actividad cuando se crea el tablero Iniciado , Cerrado , Cerrado y Reagendado
                DB::insert("INSERT INTO crm.ctipo_resultado_cierre
                (estado, nombre, created_at, updated_at, tab_id)
                VALUES(true, 'Iniciado', ?, ?, ?);", [$tablero->created_at, $tablero->updated_at, $tablero->id]);

                DB::insert("INSERT INTO crm.ctipo_resultado_cierre
                (estado, nombre, created_at, updated_at, tab_id)
                VALUES(true, 'Cerrado', ?, ?, ?);", [$tablero->created_at, $tablero->updated_at, $tablero->id]);

                DB::insert("INSERT INTO crm.ctipo_resultado_cierre
                (estado, nombre, created_at, updated_at, tab_id)
                VALUES(true, 'Cerrado y Reagendado', ?, ?, ?);", [$tablero->created_at, $tablero->updated_at, $tablero->id]);

                $usuGeneral = DB::select("SELECT * FROM public.users WHERE name = 'USUARIO GENERAL {$tablero->nombre} {$tablero->id}'");

                DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$usuGeneral[0]->id, $tablero->id]);

                return $tablero;
            });

            $dataRe = Tablero::with('tableroUsuario.usuario.departamento')->where('id', $t->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateTablero(Request $request, $id)
    {
        try {
            $eliminados = $request->input('eliminados');
            $usuarios = $request->input('usuarios');
            $tablero = $request->all();

            //echo(json_encode($eliminados[0]['id']));
            $tab = DB::transaction(function () use ($tablero, $id, $eliminados, $usuarios) {
                Tablero::where('id', $id)
                    ->update([
                        'dep_id' => $tablero['dep_id'],
                        'titab_id' => $tablero['titab_id'],
                        'nombre' => $tablero['nombre'],
                        'descripcion' => $tablero['descripcion'],
                        'estado' => $tablero['estado'],
                    ]);

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.tablero_user WHERE tab_id = " . $id . " and user_id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($usuarios); $i++) {
                    $tabl = TableroUsuario::where('tab_id', $id)->where('user_id', $usuarios[$i])->first();
                    if (!$tabl) {
                        DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$usuarios[$i]['id'], $id]);
                    }
                }

                return $tablero;
            });

            // $dataRe = Tablero::with('tableroUsuario.usuario')->where('id', $id)->get();
            $dataRe = Tablero::with('tableroUsuario.usuario.departamento')->where('id', $tab['id'])->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listTableroMisCasos($user_id)
    {
        try {

            $data = VistaMisCasos::with('miembros.usuario.departamento', 'estadodos')->where('id_usuario_miembro', $user_id)->get();

            // $data1 = DB::select("select
            // u.id as id_usuario_miembro,  u.name as usuario_miembro,u2.name as dueno_caso, cs.nombre as nombre, cs.id as caso_id,
            // cs.fecha_vencimiento, cs.created_at,ent.ent_id, (ent.ent_apellidos || ' '|| ent.ent_nombres) as cliente, f.nombre  as fase_nombre, f.color_id as fase_color, cs.prioridad,
            // cg.uniqd, cg.nombre as nombre_grupo_chat, f.tab_id,tab.nombre, cs.estado_2
            // from crm.miembros m
            // inner join crm.caso cs on cs.id = m.caso_id
            // inner join public.users u on u.id = m.user_id
            // inner join public.users u2 on u2.id = cs.user_id
            // inner join crm.fase f on f.id = cs.fas_id
            // inner join crm.tablero tab on tab.id = f.tab_id
            // inner join public.clienteCrm ent on ent.ent_id = cs.ent_id
            // inner join crm.chat_groups cg on cg.id = m.chat_group_id
            // where u.id = " . $user_id . "
            // order By caso_id DESC");




            // $data = (object) [
            //     "miscasos" => $usuarios,
            //     "miembros" => $departamentos,
            //     "tableros" => $tableros,
            //     "depUserTablero" => null
            // ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editMiembrosByTableroId($id)
    {
        try {
            $tablero = Tablero::where('id', $id)->with('tableroUsuario.usuario.departamento')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tablero));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}