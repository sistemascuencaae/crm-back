<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// JGSJ MIDDLEWARE VERIFICAR VERSION CRM
class VerificarVersionCrm
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Obtener versión enviada desde el frontend en el header
            $versionFrontend = $request->header('X-App-Version');

            // Si no envía versión, denegar acceso
            if (empty($versionFrontend)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Versión del sistema no detectada. Por favor, actualiza la aplicación presionando CTRL + F5.',
                ], 426); // 426 Upgrade Required
            }

            // Obtener versión actual requerida desde la base de datos
            $parametro = DB::selectOne("SELECT p.valor, p.nombre, p.descripcion
                                        FROM crm.parametro p
                                        WHERE p.abreviacion = 'VCRM'");

            if (!$parametro) {
                // Si no existe el parámetro, permitir acceso (para no romper el sistema)
                // Log::warning('Parámetro VCRM no encontrado en base de datos');
                return $next($request);
            }

            $versionRequerida = trim($parametro->valor);

            // DEBUG: Log temporal para ver qué está pasando
            Log::info('VERSION CHECK', [
                'frontend' => $versionFrontend,
                'frontend_length' => strlen($versionFrontend),
                'bd' => $versionRequerida,
                'bd_length' => strlen($versionRequerida),
                'son_iguales' => $versionFrontend === $versionRequerida
            ]);

            // Comparar versiones - deben ser EXACTAMENTE iguales
            if ($versionFrontend !== $versionRequerida) {
                return response()->json([
                    'status' => 'version_error',
                    'message' => 'La versión de tu aplicación no coincide con la versión actual del sistema. Por favor, actualiza la aplicación.',
                    'data' => [
                        'version_actual_app' => $versionFrontend,
                        'version_requerida' => $versionRequerida,
                        'debe_actualizar' => true,
                        'instruccion' => 'Presiona CTRL + F5 (Windows/Linux) o CMD + SHIFT + R (Mac) para actualizar'
                    ]
                ], 426); // 426 Upgrade Required
            }

            // Versión correcta, continuar normalmente
            return $next($request);

        } catch (\Exception $e) {
            // Si hay error al verificar versión, loggear pero permitir acceso
            // Log::error('Error en VerificarVersionCrm middleware: ' . $e->getMessage());
            return $next($request);
        }
    }

}
