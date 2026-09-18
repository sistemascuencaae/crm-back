<?php

namespace App\Http\Controllers\User;

use App\Events\TableroEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\UsuarioCHorario;
use App\Models\crm\Almacen;
use App\Models\openceo\Usuario;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{

    // public function __construct()
    // {
    // $this->middleware('auth:api', [
    //     'except' =>
    //         [
    //             'allUsers'
    //         ]
    // ]);
    // }

    public function listAnalistas($tableroId)
    {
        $data = DB::select("SELECT 
                                        u.*,
                                        (u.usu_alias || ' - ' || u.surname || ' ' || u.name || ' - ' || dep.dep_nombre) AS user_dep,
                                        COALESCE(array_to_json(array_agg(ua.alm_id)), '[]'::json) AS agencias_ids
                                    FROM crm.tablero ta
                                        INNER JOIN crm.tablero_user tu ON tu.tab_id = ta.id
                                        INNER JOIN crm.users u ON u.id = tu.user_id
                                        INNER JOIN crm.departamento dep ON dep.id = u.dep_id
                                        LEFT JOIN crm.usuario_almacen ua ON ua.user_id = u.id
                                    WHERE ta.id = ?
                                    GROUP BY u.id, u.usu_alias, u.surname, u.name, dep.dep_nombre;", [$tableroId]);

        foreach ($data as &$item) {
            // Convertir el string JSON a array
            $item->agencias_ids = json_decode($item->agencias_ids, true);

            // Si viene [null] o null, convertirlo en array vacío
            if (!$item->agencias_ids || $item->agencias_ids === [null]) {
                $item->agencias_ids = [];
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }

    public function listUsuariosActivos()
    {
        try {
            $usuarios = User::selectRaw("*, CONCAT(usu_alias, ' - ', name, ' ', surname) as full_name")
                ->orderBy("id", "asc")
                ->where('estado', true)
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia')->get();

            // mapeado mapeo
            // return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios activos', [
            //     "usuarios" => $usuarios->map(function ($usuario) {
            //         return [
            //             "id" => $usuario->id,
            //             "name" => $usuario->name
            //         ];
            //     }),
            // ]));

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuariosActivos2()
    {
        try {
            $usuarios = User::where('estado', true)->orderBy("name", "asc")->get(["id", "usu_alias", "name", "surname"]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function allUsers()
    {
        try {
            $usuarios = User::orderBy("id", "asc")->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia', 'horario.chorario', 'usuario_crea_actualiza')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addUser(Request $request)
    {
        try {
            // Verificar si ya existe un usuario con el mismo usu_alias
            $existingUsuAlias = User::where('usu_alias', $request->input('usu_alias'))->first();
            $existingUserCedula = User::where('cedula', $request->input('cedula'))->first();

            if ($existingUsuAlias) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este usu_alias ya existe', ''));
            }

            if ($existingUserCedula) {
                return response()->json(RespuestaApi::returnResultado('error', 'Esta cédula ya existe', ''));
            }

            // SQL para sacar solo la columna emp_id del dynamo
            // SELECT emp.emp_id
            // ROM entidad ent
            // JOIN empleado emp ON ent.ent_id = emp.ent_id
            // WHERE ent.ent_identificacion LIKE '%0102281953%';

            $emp_id = DB::table('public.entidad')
                ->join('public.empleado', 'entidad.ent_id', '=', 'empleado.ent_id')
                ->where('entidad.ent_identificacion', 'LIKE', '%' . $request->cedula . '%')
                ->pluck('empleado.emp_id')
                ->first();

            if ($emp_id) {
                $request->merge(['emp_id' => $emp_id]);
            }

            $request->merge(['id_usu_crea_actualiza' => auth()->id()]);

            // Si no existe, crea el nuevo usuario
            $newUserData = User::create($request->all());

            // if ($request->input('bod_id')) {
            //     $bodConsigUser = DB::selectOne("SELECT
            //     b2.bod_id as bod_add FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
            //     LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
            //     WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$newUserData->id]);
            //     $newUserData->bod_id_dos = $bodConsigUser->bod_add;
            //     $newUserData->save();
            // }

            if ($request->input('bod_id')) {
                $bodConsigUser = DB::selectOne("SELECT
                b2.bod_id as bod_add, b3.bod_id as bod_add2 FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
                LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                LEFT JOIN public.bodega b3 ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$newUserData->id]);
                $newUserData->bod_id_dos = $bodConsigUser->bod_add;
                $newUserData->bod_id_tres = $bodConsigUser->bod_add2;
                $newUserData->save();
            }

            if ($newUserData->id) {
                UsuarioCHorario::create([
                    "user_id" => $newUserData->id,
                    "chorario_id" => 3, // 3 es el ID del horario default en la tabla crm.chorario
                ]);
            }

            $usuarios = User::orderBy("id", "desc")->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia', 'horario.chorario', 'usuario_crea_actualiza')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editUser(Request $request, $user_id)
    {
        try {
            $usuario = User::findOrFail($user_id);

            // Verificar si ya existe otro usuario con el mismo usu_alias
            $existingUsuAlias = User::where('usu_alias', $request->input('usu_alias'))
                ->where('id', '!=', $usuario->id)
                ->first();

            $existingUserCedula = User::where('cedula', $request->input('cedula'))
                ->where('id', '!=', $usuario->id)
                ->first();

            if ($existingUsuAlias) {
                return response()->json(RespuestaApi::returnResultado('error', 'El usu_alias ya está en uso por otro usuario', ''));
            }

            if ($existingUserCedula) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cédula ya está en uso por otro usuario', ''));
            }

            $request->merge(['id_usu_crea_actualiza' => auth()->id()]);

            $usuario->update($request->all());

            // if ($request->input('bod_id')) {
            //     $bodConsigUser = DB::selectOne("SELECT
            //     b2.bod_id as bod_add FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
            //     LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
            //     WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$usuario->id]);
            //     $usuario->bod_id_dos = $bodConsigUser->bod_add;
            //     $usuario->save();
            // }

            if ($request->input('bod_id')) {
                $bodConsigUser = DB::selectOne("SELECT
                b2.bod_id as bod_add, b3.bod_id as bod_add2 FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
                LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                LEFT JOIN public.bodega b3 ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$usuario->id]);
                $usuario->bod_id_dos = $bodConsigUser->bod_add;
                $usuario->bod_id_tres = $bodConsigUser->bod_add2;
                $usuario->save();
            }

            if ($usuario->id) {
                $existe = UsuarioCHorario::where('user_id', $user_id)->exists();

                if (!$existe) {
                    UsuarioCHorario::create([
                        "user_id" => $usuario->id,
                        "chorario_id" => 3, // 3 es el ID del horario default en la tabla crm.chorario
                    ]);
                }
            }

            $mensajeUsuDynamo = '';
            $usuDynamo = Usuario::where('usu_alias', $usuario->cedula)->orWhere('usu_alias', $usuario->usu_alias)->first();

            if ($usuDynamo) {
                $usuDynamo->update(['usu_activo' => $usuario->estado]);
                $mensajeUsuDynamo = 'Usuario del Dynamo actualizado.';
            } else {
                $mensajeUsuDynamo = 'Usuario no existe en Dynamo.';
            }

            $data = User::where('id', $usuario->id)
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia', 'horario.chorario', 'usuario_crea_actualiza')
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Usuario del CRM actualizado - ' . $mensajeUsuDynamo, $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function deleteUser($user_id)
    {
        try {
            $usuario = User::findOrFail($user_id);

            $usuario->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $usuario));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuariosByTableroId($tablero_id)
    {
        try {
            // $usuarios = User::whereHas('tablero.usuario', function ($query) use ($tablero_id) {
            $usuarios = User::whereHas('tablero', function ($query) use ($tablero_id) {
                $query->where('tab_id', $tablero_id);
            })
                ->orderBy("id", "asc")
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuarioById($user_id)
    {
        try {
            $usuario = User::where('id', $user_id)->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuario));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAlmacenes()
    {
        try {
            $almacenes = Almacen::where('alm_activo', true)->orderBy('alm_nombre')->get();
            $bodegas = DB::select("SELECT * FROM public.bodega WHERE bod_activo = true
                        AND bod_nombre NOT LIKE '%CONSIG%';");

            $data = (object)[
                'almacenes' => $almacenes,
                'bodegas' => $bodegas
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEnLineaUser(Request $request, $user_id)
    {
        try {
            $usuario = $request->all();

            $data = DB::transaction(function () use ($usuario, $user_id, $request) {

                $usuario = User::findOrFail($user_id);

                $usuario->update([
                    "en_linea" => $request->en_linea,
                ]);

                // // buscar todos mis casos en los que el usuario este
                // $query = DB::select(
                //     'SELECT caso.id FROM crm.users usuario 
                //             JOIN crm.caso caso 
                //                 ON caso.user_id = usuario.id
                //             WHERE usuario.id = ?',
                //     [$usuario->id]
                // );
                // foreach ($query as $key => $value) {
                //     $caso = new CasoController();
                //     $data = $caso->getCaso($value->id);
                //     broadcast(new TableroEvent($data));
                // }

                return User::where('id', $usuario->id)->first();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listUsers()
    {
        try {
            $usuarios = User::where('usu_alias', 'not like', 'USUARIOGENERAL%')
                ->orderBy("name", "asc")
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAlmacenes2()
    {
        try {
            $data = Almacen::where('alm_activo', true)->orderBy('alm_nombre')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
