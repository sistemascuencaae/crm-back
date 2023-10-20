<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Validator;
use Illuminate\Support\Facades\DB;

class JWTController extends Controller
{
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
            'password' => 'required|string|confirmed|min:6',
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
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

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
    public function respondWithToken($token)
    {


        $alm = DB::select('SELECT alm.alm_nombre FROM public.puntoventa pve
        inner join public.almacen alm on alm.alm_id = pve.alm_id where pve.pve_id = ?', [auth('api')->user()->pve_id,]);

        $alm_nombre = '';

        if (sizeof($alm) > 0) {
            $alm_nombre = $alm[0]->alm_nombre;
        }

        // echo (json_encode($alm_nombre[0]->alm_nombre));
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60 * 60 * 24 * 2000000,
            'user' => [
                "id" => auth('api')->user()->id,
                "name" => auth('api')->user()->name,
                "surname" => auth('api')->user()->surname,
                "email" => auth('api')->user()->email,
                "usu_tipo_analista" => auth('api')->user()->usu_tipo_analista,
                "usu_tipo" => auth('api')->user()->usu_tipo,
                "usu_alias" => auth('api')->user()->usu_alias,
                "dep_id" => auth('api')->user()->dep_id,
                "profile_id" => auth('api')->user()->profile_id,
                "alm_nombre" => $alm_nombre,
            ]
        ]);



    }
}
