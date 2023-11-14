<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
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
            $usuarios = User::orderBy("id", "asc")->where('estado', true)->with('Departamento', 'perfil_analista', 'perfil')->get();

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
            $usuarios = User::orderBy("id", "asc")->with('Departamento', 'perfil_analista', 'perfil')->get();

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
            // Verificar si ya existe un usuario con el mismo correo electrónico
            $existingUser = User::where('usu_alias', $request->input('usu_alias'))->first();

            if ($existingUser) {
                return response()->json(RespuestaApi::returnResultado('error', 'El usuario ya existe', ''));
            }

            // Si no existe, crea el nuevo usuario
            User::create($request->all());

            $usuarios = User::orderBy("id", "desc")->with('Departamento', 'perfil_analista', 'perfil')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    // public function editUser(Request $request, $user_id)
    // {
    //     try {
    //         $usuario = User::findOrFail($user_id);

    //         $usuario->update($request->all());

    //         $data = User::where('id', $usuario->id)->with('Departamento', 'perfil_analista')->first();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function editUser(Request $request, $user_id)
    {
        try {
            $usuario = User::findOrFail($user_id);

            // Verificar si ya existe otro usuario con el mismo correo electrónico
            $existingUser = User::where('usu_alias', $request->input('usu_alias'))
                ->where('id', '!=', $usuario->id)
                ->first();

            if ($existingUser) {
                return response()->json(RespuestaApi::returnResultado('error', 'El correo electrónico ya está en uso por otro usuario', ''));
            }

            $usuario->update($request->all());

            $data = User::where('id', $usuario->id)->with('Departamento', 'perfil_analista', 'perfil')->first();

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
                ->with('Departamento', 'perfil_analista', 'perfil')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuarioById($user_id)
    {
        try {
            $usuario = User::where('id', $user_id)->with('Departamento', 'perfil_analista', 'perfil')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuario));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
