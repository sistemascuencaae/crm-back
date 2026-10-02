<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\BitacoraServidor;
use App\Models\Servidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use phpseclib3\Net\SSH2;
use Exception;

class ServidorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listarServidores()
    {
        try {
            $servidores = Servidor::where('estado', true)
                ->with(['zona', 'ultimoReinicio.usuario'])
                ->orderBy('nombre', 'asc')
                ->get()
                ->map(function ($servidor) {
                    $servidor->agencias = $servidor->zona
                        ? $servidor->zona->agencia->pluck('nombre')->join(', ')
                        : '';
                    $servidor->ultimo_reinicio = $servidor->ultimoReinicio
                        ? [
                            'usuario'   => $servidor->ultimoReinicio->usuario->name . ' ' . $servidor->ultimoReinicio->usuario->surname ?? 'N/A',
                            'resultado' => $servidor->ultimoReinicio->resultado,
                            'fecha'     => $servidor->ultimoReinicio->created_at,
                        ]
                        : null;
                    return $servidor->makeHidden(['zona', 'ultimoReinicio']);
                });

            return response()->json(RespuestaApi::returnResultado('success', 'Servidores obtenidos', $servidores));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener servidores', $e->getMessage()));
        }
    }

    public function reiniciarServidor(Request $request)
    {
        $log = new Funciones();
        $servidorId = $request->input('servidor_id');

        $servidor = Servidor::where('id', $servidorId)
            ->where('estado', true)
            ->first();

        if (!$servidor) {
            return response()->json(RespuestaApi::returnResultado('error', 'Servidor no encontrado o inactivo', null));
        }

        $host = $servidor->ip;

        try {
            $ssh = new SSH2($host);

            $loginExitoso = $ssh->login($servidor->usuario, $servidor->clave);

            if (!$loginExitoso) {
                $log->logError(ServidorController::class, 'No se pudo autenticar en el servidor ' . $host);

                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo autenticar en el servidor ' . $host, null));
            }

            // Ejecutar el script desacoplado de la sesión SSH
            $ssh->exec('nohup bash ' . $servidor->archivo . ' > /dev/null 2>&1 &');

            // Verificar cada 5 segundos si el puerto 9191 levantó (máximo 90 segundos)
            $intentos = 0;
            $maxIntentos = 18; // 18 intentos x 5 segundos = 90 segundos máximo
            $intervalo = 5;
            $portCheck = '';

            while ($intentos < $maxIntentos) {
                sleep($intervalo);
                $portCheck = trim($ssh->exec('fuser -n tcp 9191 2>/dev/null'));

                if ($portCheck !== '') {
                    break;
                }

                $intentos++;
            }

            $tiempoSegundos = ($intentos + 1) * $intervalo;

            if ($portCheck !== '') {
                $log->logInfo(ServidorController::class, 'Servidor ' . $host . ' levantado en puerto 9191 tras ' . $tiempoSegundos . ' segundos');

                BitacoraServidor::create([
                    'servidor_id'     => $servidor->id,
                    'user_id'         => Auth::id(),
                    'resultado'       => 'success',
                    'detalle'         => 'Servidor levantado en puerto 9191 tras ' . $tiempoSegundos . ' segundos',
                    'tiempo_segundos' => $tiempoSegundos,
                ]);

                return response()->json(RespuestaApi::returnResultado('success', 'Servidor ' . $servidor->nombre . ' levantado correctamente', null));
            } else {
                $log->logError(ServidorController::class, 'El puerto 9191 no respondió en el servidor ' . $host . ' tras 90 segundos');

                BitacoraServidor::create([
                    'servidor_id'     => $servidor->id,
                    'user_id'         => Auth::id(),
                    'resultado'       => 'error',
                    'detalle'         => 'El puerto 9191 no respondió en el servidor ' . $host . ' tras 90 segundos',
                    'tiempo_segundos' => 90,
                ]);

                return response()->json(RespuestaApi::returnResultado('error', 'El puerto 9191 no levantó en ' . $servidor->nombre . ' tras 90 segundos', null));
            }
        } catch (Exception $e) {
            $log->logError(ServidorController::class, 'Error al ejecutar script en ' . $host, $e);

            BitacoraServidor::create([
                'servidor_id'     => $servidor->id,
                'user_id'         => Auth::id(),
                'resultado'       => 'error',
                'detalle'         => 'Excepción: ' . $e->getMessage(),
                'tiempo_segundos' => null,
            ]);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al ejecutar el script en ' . $host, $e->getMessage()));
        }
    }

    public function getStatsAllServidores()
    {
        $servidores = Servidor::where('estado', true)->get();
        $resultado = [];

        foreach ($servidores as $servidor) {
            try {
                $ssh = new SSH2($servidor->ip);

                if (!$ssh->login($servidor->usuario, $servidor->clave)) {
                    $resultado[$servidor->id] = ['error' => $servidor->ip . ' No se pudo autenticar'];
                    continue;
                }

                $output = $ssh->exec('free -b && echo "|||" && cat /proc/loadavg && echo "|||" && nproc');
                $partes = explode('|||', $output);

                $lines = preg_split('/\n/', trim($partes[0]));
                $formatoAntiguo = isset($lines[2]) && strpos(trim($lines[2]), '-/+') === 0;
                $mem = preg_split('/\s+/', trim($lines[1]));
                $ramTotal = (int) $mem[1];
                $ramUsed = $formatoAntiguo ? (int) preg_split('/\s+/', trim($lines[2]))[1] : (int) $mem[2];
                $ramPorcentaje = $ramTotal > 0 ? round($ramUsed / $ramTotal * 100, 1) : 0;

                $loadParts = explode(' ', trim($partes[1]));
                $load1min  = (float) ($loadParts[0] ?? 0);
                $nproc     = (int) trim($partes[2]);
                $cpuPorcentaje = $nproc > 0 ? round($load1min / $nproc * 100, 1) : 0;

                $resultado[$servidor->id] = [
                    'ram' => ['total' => $ramTotal, 'used' => $ramUsed, 'porcentaje' => $ramPorcentaje],
                    'cpu' => ['nucleos' => $nproc, 'porcentaje' => $cpuPorcentaje],
                ];
            } catch (Exception $e) {
                $resultado[$servidor->id] = ['error' => $e->getMessage()];
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Stats obtenidos', $resultado));
    }
}
