<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PowerBiRefreshDatasetController extends Controller
{
    private function getAccessToken()
    {
        $tenantId     = env('POWERBI_TENANT_ID');
        $clientId     = env('POWERBI_CLIENT_ID');
        $clientSecret = env('POWERBI_CLIENT_SECRET');

        if (!$tenantId || !$clientId || !$clientSecret) {
            throw new \Exception('Configuración de Power BI incompleta. Verifica el archivo .env');
        }

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://analysis.windows.net/powerbi/api/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Error al obtener token de Power BI: ' . $response->body());
        }

        return $response->json()['access_token'];
    }

    public function getRefreshStatus(Request $request)
    {
        try {
            $request->validate([
                'workspace_id' => 'required|string',
                'dataset_id'   => 'required|string',
            ]);

            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->get("https://api.powerbi.com/v1.0/myorg/groups/{$request->workspace_id}/datasets/{$request->dataset_id}/refreshes?\$top=1");

            if ($response->successful()) {
                $latest = $response->json()['value'][0] ?? null;
                return response()->json([
                    'status'         => 'success',
                    'refresh_status' => $latest ? $latest['status'] : 'Unknown',
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener estado: ' . $response->body(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function refreshDataset(Request $request)
    {
        try {
            $request->validate([
                'workspace_id' => 'required|string',
                'dataset_id'   => 'required|string',
            ]);

            $workspaceId = $request->workspace_id;
            $datasetId   = $request->dataset_id;

            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->post("https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/datasets/{$datasetId}/refreshes");

            // 202 = refresh iniciado correctamente
            if ($response->status() === 202) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Actualización del dataset iniciada correctamente.',
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al refrescar el dataset: ' . $response->body(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
