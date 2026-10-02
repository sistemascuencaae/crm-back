<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lee datos de una hoja de cálculo privada de Google usando una cuenta de
 * servicio (Service Account). No requiere el SDK google/apiclient: firma un
 * JWT con firebase/php-jwt, lo canjea por un access token OAuth2 y consulta
 * la API de Google Sheets v4 con Guzzle/Http (mismo patrón que Power BI).
 */
class GoogleSheetsController extends Controller
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/spreadsheets.readonly';

    /**
     * Obtiene (y cachea ~55 min) un access token para la cuenta de servicio.
     */
    private function getAccessToken()
    {
        return Cache::remember('google_sheets_access_token', 3300, function () {
            $credentials = $this->loadCredentials();

            $now = time();
            $payload = [
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => self::TOKEN_URI,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ];

            $assertion = JWT::encode($payload, $credentials['private_key'], 'RS256');

            $response = Http::asForm()->post(self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Error al obtener token de Google: ' . $response->body());
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Carga y valida el JSON de la cuenta de servicio.
     */
    private function loadCredentials()
    {
        // Opción A: JSON completo (Base64) en el .env → GOOGLE_SHEETS_CREDENTIALS_JSON
        $base64 = config('services.google_sheets.credentials_json');
        if (!empty($base64)) {
            $json = base64_decode($base64, true);
            $data = $json ? json_decode($json, true) : null;

            if (empty($data['client_email']) || empty($data['private_key'])) {
                throw new \Exception('GOOGLE_SHEETS_CREDENTIALS_JSON no contiene un JSON válido. Verifica que pegaste TODO el texto en Base64 sin cortarlo.');
            }

            return $data;
        }

        // Opción B: archivo .json en disco → GOOGLE_SHEETS_CREDENTIALS (o el default)
        $path = config('services.google_sheets.credentials');

        if (!$path || !file_exists($path)) {
            throw new \Exception('No se encontraron credenciales de Google. Define GOOGLE_SHEETS_CREDENTIALS_JSON (Base64) en el .env, o coloca el archivo en storage/app/google/service-account.json');
        }

        $data = json_decode(file_get_contents($path), true);

        if (empty($data['client_email']) || empty($data['private_key'])) {
            throw new \Exception('El JSON de la cuenta de servicio de Google es inválido (faltan client_email o private_key).');
        }

        return $data;
    }

    /**
     * Lee un rango de una hoja y devuelve las filas.
     *
     * Params (query o body):
     *   - range           : requerido. Ej: "Hoja1!A1:F" o "Hoja1" (toda la hoja)
     *   - spreadsheet_id  : opcional. Si no se envía, usa el del .env
     *   - as_objects      : opcional (bool). Si true, usa la 1ra fila como
     *                       encabezados y devuelve un arreglo de objetos.
     */
    public function read(Request $request)
    {
        try {
            $request->validate([
                'range'          => 'required|string',
                'spreadsheet_id' => 'nullable|string',
                // Sin regla 'boolean': por querystring llega el string "true"/"false".
                // $request->boolean() más abajo lo interpreta correctamente.
                'as_objects'     => 'nullable',
            ]);

            $spreadsheetId = $request->input('spreadsheet_id', config('services.google_sheets.spreadsheet_id'));

            if (!$spreadsheetId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Falta spreadsheet_id (ni en el request ni en GOOGLE_SHEETS_SPREADSHEET_ID).',
                ], 422);
            }

            $token = $this->getAccessToken();

            $response = Http::withToken($token)->get(
                "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($request->range)
            );

            if (!$response->successful()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Error al leer la hoja: ' . $response->body(),
                ], $response->status());
            }

            $values = $response->json()['values'] ?? [];

            if ($request->boolean('as_objects')) {
                $values = $this->mapToObjects($values);
            }

            return response()->json([
                'status' => 'success',
                'count'  => count($values),
                'data'   => $values,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Datos inválidos: ' . implode(' ', $e->validator->errors()->all()),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lee VARIOS rangos (de una o varias pestañas) en UNA sola llamada.
     *
     * Params (query o body):
     *   - ranges          : requerido. Varios rangos. Acepta:
     *                       * arreglo:  ranges[]=Hoja1!A1:F&ranges[]=Hoja2!A1:C
     *                       * o texto separado por comas: "Hoja1!A1:F,Hoja2!A1:C"
     *   - spreadsheet_id  : opcional. Si no se envía, usa el del .env
     *   - as_objects      : opcional (bool). 1ra fila de cada rango = encabezados.
     *
     * Devuelve data[] con un objeto por rango: { range, count, data }.
     */
    public function batchRead(Request $request)
    {
        try {
            $request->validate([
                'ranges'         => 'required',
                'spreadsheet_id' => 'nullable|string',
                'as_objects'     => 'nullable',
            ]);

            // Normaliza a arreglo de rangos (acepta arreglo o string con comas).
            $ranges = $request->input('ranges');
            if (is_string($ranges)) {
                $ranges = array_filter(array_map('trim', explode(',', $ranges)));
            }
            $ranges = array_values(array_filter((array) $ranges));

            if (empty($ranges)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Debes enviar al menos un rango en "ranges".',
                ], 422);
            }

            $spreadsheetId = $request->input('spreadsheet_id', config('services.google_sheets.spreadsheet_id'));

            if (!$spreadsheetId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Falta spreadsheet_id (ni en el request ni en GOOGLE_SHEETS_SPREADSHEET_ID).',
                ], 422);
            }

            $token = $this->getAccessToken();

            // values:batchGet admite varios ?ranges= en la misma llamada.
            $query = 'ranges=' . implode('&ranges=', array_map('rawurlencode', $ranges));

            $response = Http::withToken($token)->get(
                "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values:batchGet?{$query}"
            );

            if (!$response->successful()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Error al leer las hojas: ' . $response->body(),
                ], $response->status());
            }

            $asObjects   = $request->boolean('as_objects');
            $valueRanges = $response->json()['valueRanges'] ?? [];

            $data = array_map(function ($vr) use ($asObjects) {
                $values = $vr['values'] ?? [];
                if ($asObjects) {
                    $values = $this->mapToObjects($values);
                }
                return [
                    'range' => $vr['range'] ?? null,
                    'count' => count($values),
                    'data'  => $values,
                ];
            }, $valueRanges);

            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Datos inválidos: ' . implode(' ', $e->validator->errors()->all()),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convierte filas crudas en objetos usando la 1ra fila como encabezados.
     */
    private function mapToObjects(array $values): array
    {
        if (count($values) === 0) {
            return [];
        }

        $headers = array_shift($values);

        return array_map(function ($row) use ($headers) {
            $row = array_pad($row, count($headers), null);
            return array_combine($headers, array_slice($row, 0, count($headers)));
        }, $values);
    }
}
