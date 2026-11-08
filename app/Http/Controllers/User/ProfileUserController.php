<?php

namespace App\Http\Controllers\User;

use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\User\ProfileUserGeneralResource;
use App\Http\Resources\RespuestaApi; // 07-nov: respuesta de la opción desactivada

class ProfileUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // 07-nov: DESACTIVADO. Editaba SIEMPRE al usuario 4 con todo el body (usu_tipo, profile_id, alm_id, password…),
    // nadie lo llama desde el front y además reventaba (la clase App\User no existe). Las rutas
    // POST /api/users/profile-user y PUT /api/users/profile quedan respondiendo este error.
    // Si algún día hace falta «mi perfil», que use auth('api')->user() y una lista cerrada de campos.
    public function profile_user(Request $request)
    {
        return response()->json(RespuestaApi::returnResultado('error', 'Esta opción está desactivada.', null));

        // DESACTIVADO 07-nov: usuario fijo (4) y asignación masiva con lo que mandara el front
        // $user = auth('api')->user();
        // $userModel =  User::findOrFail(4);
        // if($request->hasFile("imagen")){
        //     if($userModel->avatar){
        //         Storage::delete($userModel->avatar);
        //     }
        //     $path = Storage::putFile('users',$request->file("imagen"));
        //     $request->request->add(["avatar" => $path]);
        // }
        // $userModel->update($request->all());
        // return response()->json(["message"=> 200, "user" => ProfileUserGeneralResource::make($userModel)]);
    }

    public function contactUsers()
    {
        $users = User::where("id","<>",auth("api")->user()->id)->orderBy("id","desc")->get();

        return response()->json(["users" => $users->map(function($user){
                return ProfileUserGeneralResource::make($user);
            }),
        ]);
    }
}





/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*----------------- METODO PARA ENCRIPTAR TODAS LAS CONTRASEÑAS -----------------------*/
/*---------------------------------NO BORRAR-------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
//                                INICIO
// public function profile_user(Request $request)
// {

//     //$user = auth('api')->user();
//     $usuarios = DB::select('select * from crm.users');
//     //echo('usuer : '.json_encode($request->all()));

//     for ($i = 0; $i < sizeof($usuarios); $i++) {
//         $data = array([
//             "email" => $usuarios[$i]->email,
//             "password" => $usuarios[$i]->password,
//             "id" => $usuarios[$i]->id,
//             "password_confirmation" => $usuarios[$i]->password,
//         ])[0];

//         //echo('usuer : '.json_encode($data));
//         //echo('usuer : '.json_encode($usuarios));
//         //$id = $request->input('id');
//         $userModel =  User::findOrFail($usuarios[$i]->id);
//         if ($request->hasFile("imagen")) {
//             if ($userModel->avatar) {
//                 Storage::delete($userModel->avatar);
//             }
//             $path = Storage::putFile('users', $request->file("imagen"));
//             $request->request->add(["avatar" => $path]);
//         }
//         $userModel->update($data);
//     }






//     // "email" => "aguerrero@gmail.com";
//     // "password" => "123456";
//     // "id" => "1378",
//     // "password_confirmation" => "123456"











//     // echo('usuer : '.json_encode($usuarios));
//     // $id = $request->input('id');
//     // $userModel =  User::findOrFail($id);
//     // if($request->hasFile("imagen")){
//     //     if($userModel->avatar){
//     //         Storage::delete($userModel->avatar);
//     //     }
//     //     $path = Storage::putFile('users',$request->file("imagen"));
//     //     $request->request->add(["avatar" => $path]);
//     // }
//     // $userModel->update($request->all());
//     // return response()->json(["message"=> 200, "user" => ProfileUserGeneralResource::make($userModel)]);
// }
//                        FIN
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*----------------- METODO PARA ENCRIPTAR TODAS LAS CONTRASEÑAS -----------------------*/
/*---------------------------------NO BORRAR-------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
/*-------------------------------------------------------------------------------------*/
