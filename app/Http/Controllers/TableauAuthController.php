<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

class TableauAuthController extends Controller
{
    // !Generar token JWT para Tableau Cloud
    public function generateTokenTableau(Request $request)
    {
        try {
            // Configuración de Tableau Connected App desde .env
            $clientId = env('TABLEAU_CLIENT_ID');
            $secretId = env('TABLEAU_SECRET_ID');
            $secretValue = env('TABLEAU_SECRET_VALUE');
            $userEmail = env('TABLEAU_USER_EMAIL'); // Email del usuario de Tableau

            // Validar que existan las credenciales
            if (!$clientId || !$secretId || !$secretValue) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Configuración de Tableau incompleta. Verifica el archivo .env'
                ], 500);
            }

            // Crear payload del JWT
            $payload = [
                'iss' => $clientId,
                'exp' => time() + (10 * 60), // Token expira en 10 minutos
                'jti' => Uuid::uuid4()->toString(),
                'aud' => 'tableau',
                'sub' => $userEmail,
                'scp' => ['tableau:views:embed', 'tableau:metrics:embed']
            ];

            // Crear header del JWT
            $headers = [
                'kid' => $secretId,
                'iss' => $clientId
            ];

            // Generar el token JWT
            $token = JWT::encode($payload, $secretValue, 'HS256', null, $headers);

            return response()->json([
                'status' => 'success',
                'message' => 'Token generado correctamente.',
                'token' => $token,
                'expires_in' => 600 // 10 minutos en segundos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar token: ' . $e->getMessage()
            ], 500);
        }
    }
}
