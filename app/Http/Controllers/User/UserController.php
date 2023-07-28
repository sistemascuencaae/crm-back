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
        inner join public.users u on u.id  = tu.user_id
        inner join crm.departamento dep on dep.id = u.dep_id
        where ta.id = " . $tableroId);
        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }

    public function listUsuariosActivos()
    {
        try {
            $usuarios = User::orderBy("id", "asc")->where('estado', true)->with('Departamento')->get();

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
            $usuarios = User::orderBy("id", "asc")->with('Departamento')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addUser(Request $request)
    {
        try {
            User::create($request->all());

            $usuarios = User::orderBy("id", "desc")->with('Departamento')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editUser(Request $request, $user_id)
    {
        try {
            $usuario = User::findOrFail($user_id);

            $usuario->update($request->all());

            $data = User::where('id', $usuario->id)->with('Departamento')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
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

}