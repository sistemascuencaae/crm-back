<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Controllers\JWTController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class EquifaxController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function loginEquifax(Request $request)
    {
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




    public function getDocuments(Request $request)
    {
        try {
            // Validar el token de autorización OAuth
            $token = $request->header('Authorization');

            // Realizar validación de OAuth según tus necesidades

            // Obtener y validar el JSON de la solicitud
            $jsonTransaction = $request->json();

            // Verificar si se proporcionó un JSON válido
            if (empty($jsonTransaction)) {
                return response()->json(['message' => 'Parámetros mal formateados'], 400);
            }

            // Ruta de la carpeta donde se encuentran los archivos PDF
            $folderPath = storage_path('app/public/equifax/');

            // Verificar si la carpeta existe
            if (!File::isDirectory($folderPath)) {
                return response()->json(['message' => 'La carpeta no existe'], 404);
            }

            // Obtener la lista de archivos PDF en la carpeta
            $pdfFiles = File::files($folderPath);

            // Inicializar un array para almacenar los documentos en base64
            $documents = [];

            // Recorrer los archivos PDF y convertirlos en base64
            foreach ($pdfFiles as $pdfFile) {
                if (pathinfo($pdfFile, PATHINFO_EXTENSION) === 'pdf') {
                    // Leer el archivo PDF y convertirlo en base64
                    $pdfData = file_get_contents($pdfFile);
                    $base64Data = base64_encode($pdfData);

                    // Agregar el documento base64 al array
                    $documents[] = $base64Data;
                }
            }

            // Verificar si hay documentos disponibles
            if (count($documents) > 0) {
                return response()->json($documents, 200);
            } else {
                return response()->json(['message' => 'No hay archivos PDF disponibles para retornar'], 404);
            }
        } catch (\Exception $e) {
            // En caso de excepción, retorna un error interno del servidor
            return response()->json(['message' => 'Ha ocurrido un error en el servidor'], 500);
        }
    }





}