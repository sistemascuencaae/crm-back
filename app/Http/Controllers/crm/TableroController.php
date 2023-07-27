<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tablero;
use App\Models\crm\TableroUsuario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableroController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

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

    public function listTableroInactivos()
    {
        $tableros = Tablero::with('tableroUsuario.usuario.departamento')->where('estado', false)->orderBy("id", "desc")->get();

        return response()->json([
            "tableros" => $tableros,
        ]);
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

                DB::insert("INSERT INTO crm.fase
                (tab_id, nombre, descripcion, estado, orden, created_at, updated_at, generar_caso, color_id)
                VALUES(?, 'BANDEJA DE ENTRADA', 'SE CARGARAN TODAS LOS CASOS SIN ASIGNAR', true, 1, ?, ?, false, 22);",[$tablero->id, $tablero->created_at, $tablero->updated_at]);
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
            // $data = DB::select("select cg.uniqd as clave_chat, c.id as id_caso,c.nombre as caso_nombre,
            // f.nombre  as fase_nombre, f.color_id as fase_color, c.prioridad,c.created_at, c.fecha_vencimiento , uprin.name as u_principal, uprin.id as id_uprincipal,
            // (ent.ent_apellidos || ' '|| ent.ent_nombres) as cliente
            // from crm.chat_groups cg
            // inner join crm.miembros m on m.chat_group_id = cg.id
            // inner join public.users u on u.id = m.user_id
            // inner join crm.caso c on c.id  = m.caso_id
            // inner join public.users uprin on uprin.id = c.user_id
            // inner join public.entidad ent on ent.ent_id = c.ent_id
            // inner join crm.fase f on f.id = c.fas_id
            // where u.id = " . $user_id);

            $data = DB::select("select
            u.id as id_usuario_miembro,  u.name as usuario_miembro,u2.name as dueno_caso, cs.nombre as nombre, cs.id as caso_id,
           cs.fecha_vencimiento, cs.created_at,ent.ent_id, (ent.ent_apellidos || ' '|| ent.ent_nombres) as cliente, f.nombre  as fase_nombre, f.color_id as fase_color, cs.prioridad,
           cg.uniqd, cg.nombre as nombre_grupo_chat, f.tab_id
           from crm.miembros m
           inner join crm.caso cs on cs.id = m.caso_id
           inner join public.users u on u.id = m.user_id
           inner join public.users u2 on u2.id = cs.user_id
           inner join crm.fase f on f.id = cs.fas_id
           inner join public.entidad ent on ent.ent_id = cs.ent_id
           inner join crm.chat_groups cg on cg.id = m.chat_group_id
           where u.id = " . $user_id . "
           order By caso_id DESC");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
