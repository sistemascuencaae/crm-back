<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Almacen;
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
        //$data = User::with('UsuarioDynamo')->where('usu_tipo_analista', 1)->get();
        $data = DB::select("SELECT u.*, (u.name || ' - ' || dep.dep_nombre) as user_dep from crm.tablero ta
        inner join crm.tablero_user tu on tu.tab_id = ta.id
        inner join crm.users u on u.id  = tu.user_id
        inner join crm.departamento dep on dep.id = u.dep_id
        where ta.id = " . $tableroId);
        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }

    public function listUsuariosActivos()
    {
        try {
            $usuarios = User::orderBy("id", "asc")->where('estado', true)->with('Departamento', 'perfil_analista', 'perfil', 'almacen')->get();

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

    public function allUsers()
    {
        try {
            $usuarios = User::orderBy("id", "asc")->with('Departamento', 'perfil_analista', 'perfil', 'almacen')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addUser(Request $request)
    // {
    //     try {
    //         User::create($request->all());

    //         $usuarios = User::orderBy("id", "desc")->with('Departamento', 'perfil_analista')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $usuarios));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

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

            // Si no existe, crea el nuevo usuario
            User::create($request->all());

            $usuarios = User::orderBy("id", "desc")->with('Departamento', 'perfil_analista', 'perfil', 'almacen')->get();

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

            $usuario->update($request->all());

            $data = User::where('id', $usuario->id)->with('Departamento', 'perfil_analista', 'perfil', 'almacen')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
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
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuarioById($user_id)
    {
        try {
            $usuario = User::where('id', $user_id)->with('Departamento', 'perfil_analista', 'perfil', 'almacen')->first();

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

                return User::where('id', $usuario->id)->first();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
