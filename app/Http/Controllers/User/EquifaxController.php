<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Controllers\JWTController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
class EquifaxController extends Controller
{

    /**
     * login user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginEquifax(Request $request){
        $credentials = $request->only('username', 'password', 'grant_type');

        // Valida que el grant_type sea 'authorization_code'
        if ($credentials['grant_type'] !== 'authorization_code') {
            return response()->json(['error' => 'Invalid grant_type'], 400);
        }

        $data = (object) [
            "email" => $credentials['username'],
            "password" => $credentials['password'],
        ];
        $usuarioArray = get_object_vars($data);

        $validator = Validator::make($usuarioArray, [
            'email' => 'required|email',
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


    public function respondWithToken($token)
    {

        $currentDate = Carbon::now();
        $issued = $currentDate->format('D, d M Y H:i:s T');
        $expiresTemp = $currentDate->addMinutes(1);
        $expires = $expiresTemp->format('D, d M Y H:i:s T');
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 1,
            '.issued' => $issued,
            '.expires' => $expires
        ]);
    }
}
