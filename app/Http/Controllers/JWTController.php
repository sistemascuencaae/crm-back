<?php

namespace App\Http\Controllers;

use App\Events\TableroEvent;
use App\Http\Controllers\crm\CasoController;
use App\Http\Resources\RespuestaApi; // 07-nov: respuesta de GET /api/access/sesion
use App\Http\Traits\SesionUsuarioTrait; // 07-nov: arma el "user" de la sesión (login, access/sesion y cabecera X-Sesion-Usuario)
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Validator;
use Illuminate\Support\Facades\DB;

class JWTController extends Controller
{
    use SesionUsuarioTrait; // 07-nov

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Register user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }

    /**
     * login user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usu_alias' => 'required|string|min:4',
            'password' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json(['error' => 'Usuario o contraseña incorrecta'], 401);
            // return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Verifica si el usuario está activo
        $user = Auth::user();
        if (!$user || !$user->estado) {
            // El usuario no está activo
            return response()->json(['error' => 'El usuario NO esta activo'], 401);
        }

        // Lineas para poner "en Linea" al usuario al iniciar sesión
        $usuario = User::findOrFail($user->id);

        $usuario->update([
            "en_linea" => true,
            'ult_inicio_sesion' => now(), // ⬅️ Esta línea guarda el último login
        ]);

        // // buscar todos mis casos en los que el usuario este
        // $query = DB::select(
        //     'SELECT caso.id FROM crm.users usuario 
        //             JOIN crm.caso caso 
        //                 ON caso.user_id = usuario.id
        //             WHERE usuario.id = ?', [$user->id]
        // );
        // foreach ($query as $key => $value) {
        //     $caso = new CasoController();
        //     $data = $caso->getCaso($value->id);
        //     broadcast(new TableroEvent($data));
        // }

        return $this->respondWithToken($token);
    }

    /**
     * Logout user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'User successfully logged out.']);
    }

    /**
     * Refresh token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get user profile.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        return response()->json(auth()->user());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */


    // Metodo original
    // public function respondWithToken($token)
    // {


    //     $alm = DB::selectOne('SELECT alm.alm_nombre, alm.alm_codigo, alm.alm_id from crm.users u
    //     inner join public.almacen alm on alm.alm_id = u.alm_id where u.id = ?', [auth('api')->user()->id]);

    //     $alm_id = null;
    //     $alm_nombre = '';
    //     $alm_codigo = 0;


    //     $accesos = DB::select("SELECT u.id as user_id, me.name, me.url from crm.users u
    //     inner join crm.profiles p on p.id = u.profile_id
    //     inner join crm.access acc on acc.profile_id = p.id and acc.ejecutar = 1
    //     inner join crm.menu me on me.id = acc.menu_id
    //     where u.id = ?;",[auth('api')->user()->id]);

    //     if ($alm) {
    //         $alm_nombre = $alm->alm_nombre;
    //         $alm_id = $alm->alm_id;
    //         $alm_codigo = $alm->alm_codigo;
    //     }

    //     $usuario = User::findOrFail(auth('api')->user()->id);

    //     $usuario->update([
    //         "en_linea" => true,
    //     ]);

    //     // echo (json_encode($alm_nombre[0]->alm_nombre));
    //     return response()->json([
    //         'access_token' => $token,
    //         'token_type' => 'bearer',
    //         'expires_in' => auth()->factory()->getTTL(),
    //         'accesos' => $accesos,
    //         'user' => [
    //             "id" => auth('api')->user()->id,
    //             "en_linea" => $usuario->en_linea,
    //             "full_name" => auth('api')->user()->usu_alias . ' - ' . auth('api')->user()->name . ' ' . auth('api')->user()->surname,
    //             "name" => auth('api')->user()->name,
    //             "surname" => auth('api')->user()->surname,
    //             "email" => auth('api')->user()->email,
    //             "usu_tipo_analista" => auth('api')->user()->usu_tipo_analista,
    //             "usu_tipo" => auth('api')->user()->usu_tipo,
    //             "usu_alias" => auth('api')->user()->usu_alias,
    //             "dep_id" => auth('api')->user()->dep_id,
    //             "profile_id" => auth('api')->user()->profile_id,
    //             "bod_id" => auth('api')->user()->bod_id,
    //             "alm_nombre" => $alm_nombre,
    //             "alm_id" => $alm_id,
    //             "alm_codigo" => $alm_codigo,
    //         ]
    //     ]);



    // }



    // DESACTIVADO 07-nov: el arreglo "user" pasó a SesionUsuarioTrait::armarUsuarioSesion (mismas llaves, mismos tipos), para que el
    // login, GET /api/access/sesion y la cabecera X-Sesion-Usuario respondan lo mismo. Reactivar = descomentar este y comentar el de abajo.
    // public function respondWithToken($token)
    // {
    //     // NO PUEDO SACAR EL ALM_CODIGO PORQUE EN MI TABLA AGENCIA NO EXISTE, POR LO TANTO LOS REPORTES DE VENTAS YA NO VAN A SERVIR
    //     $alm = DB::selectOne('SELECT a.nombre AS alm_nombre, a.codigo AS alm_id
    //                                     FROM crm.users u
    //                                 INNER JOIN crm.agencia a ON a.codigo = CAST(u.alm_id AS VARCHAR)
    //                                 WHERE u.id = ?', [auth('api')->user()->id]);
    //
    //     $alm_id = null;
    //     $alm_nombre = '';
    //
    //     $accesos = DB::select("SELECT u.id as user_id, me.name, me.url from crm.users u
    //     inner join crm.profiles p on p.id = u.profile_id
    //     inner join crm.access acc on acc.profile_id = p.id and acc.ejecutar = 1
    //     inner join crm.menu me on me.id = acc.menu_id
    //     where u.id = ?;",[auth('api')->user()->id]);
    //
    //     if ($alm) {
    //         $alm_nombre = $alm->alm_nombre;
    //         $alm_id = $alm->alm_id;
    //     }
    //
    //     $usuario = User::findOrFail(auth('api')->user()->id);
    //
    //     $usuario->update([
    //         "en_linea" => true,
    //     ]);
    //
    //     return response()->json([
    //         'access_token' => $token,
    //         'token_type' => 'bearer',
    //         'expires_in' => auth()->factory()->getTTL(),
    //         'accesos' => $accesos,
    //         'user' => [
    //             "id" => auth('api')->user()->id,
    //             "en_linea" => $usuario->en_linea,
    //             "full_name" => auth('api')->user()->usu_alias . ' - ' . auth('api')->user()->name . ' ' . auth('api')->user()->surname,
    //             "name" => auth('api')->user()->name,
    //             "surname" => auth('api')->user()->surname,
    //             "email" => auth('api')->user()->email,
    //             "usu_tipo_analista" => auth('api')->user()->usu_tipo_analista,
    //             "usu_tipo" => auth('api')->user()->usu_tipo,
    //             "usu_alias" => auth('api')->user()->usu_alias,
    //             "dep_id" => auth('api')->user()->dep_id,
    //             "profile_id" => auth('api')->user()->profile_id,
    //             "bod_id" => auth('api')->user()->bod_id,
    //             "alm_nombre" => $alm_nombre,
    //             "alm_id" => $alm_id,
    //         ]
    //     ]);
    // }

    // 07-nov: responde igual que antes (access_token, token_type, expires_in, accesos y user, con las mismas consultas y en el mismo
    // orden); solo el arreglo "user" sale de SesionUsuarioTrait.
    public function respondWithToken($token)
    {
        // NO PUEDO SACAR EL ALM_CODIGO PORQUE EN MI TABLA AGENCIA NO EXISTE, POR LO TANTO LOS REPORTES DE VENTAS YA NO VAN A SERVIR
        $alm = $this->agenciaSesionUsuario(auth('api')->user());

        $accesos = DB::select("SELECT u.id as user_id, me.name, me.url from crm.users u
        inner join crm.profiles p on p.id = u.profile_id
        inner join crm.access acc on acc.profile_id = p.id and acc.ejecutar = 1
        inner join crm.menu me on me.id = acc.menu_id
        where u.id = ?;",[auth('api')->user()->id]);

        $usuario = User::findOrFail(auth('api')->user()->id);

        $usuario->update([
            "en_linea" => true,
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL(),
            'accesos' => $accesos,
            'user' => $this->armarUsuarioSesion(auth('api')->user(), $usuario->en_linea, $alm),
        ]);
    }

    // 07-nov: GET /api/access/sesion. El usuario de la sesión con el token, armado igual que el "user" del login (sin tocar en_linea):
    // el front lo pide al arrancar, antes de pintar, porque el user ya no vive en el localStorage.
    public function sesion()
    {
        $usuario = auth('api')->user();

        return response()->json(RespuestaApi::returnResultado('success', 'Sesión del usuario', [
            'user' => $this->armarUsuarioSesion($usuario, $usuario->en_linea, $this->agenciaSesionUsuario($usuario)),
            // 08-nov: el nombre del perfil, para el aviso "te cambiaron del perfil X al perfil Y". Va aparte porque el "user" debe
            // quedar idéntico al del login.
            'perfil_nombre' => (string) (DB::table('crm.profiles')->where('id', $usuario->profile_id)->value('name') ?? ''),
        ]));
    }


}
