<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\Servidor;
use Illuminate\Http\Request;
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
                ->orderBy('nombre', 'asc')
                ->get();

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

            if ($portCheck !== '') {
                $log->logInfo(ServidorController::class, 'Servidor ' . $host . ' levantado en puerto 9191 tras ' . ($intentos + 1) * $intervalo . ' segundos');

                return response()->json(RespuestaApi::returnResultado('success', 'Servidor ' . $servidor->nombre . ' levantado correctamente', null));
            } else {
                $log->logError(ServidorController::class, 'El puerto 9191 no respondió en el servidor ' . $host . ' tras 90 segundos');
                return response()->json(RespuestaApi::returnResultado('error', 'El puerto 9191 no levantó en ' . $servidor->nombre . ' tras 90 segundos', null));
            }
        } catch (Exception $e) {
            $log->logError(ServidorController::class, 'Error al ejecutar script en ' . $host, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al ejecutar el script en ' . $host, $e->getMessage()));
        }
    }

    public function getStatsAllServidores()
    {
        $servidores = Servidor::where('estado', true)->get();
        $resultado  = [];

        foreach ($servidores as $servidor) {
            try {
                $ssh = new SSH2($servidor->ip);

                if (!$ssh->login($servidor->usuario, $servidor->clave)) {
                    $resultado[$servidor->id] = ['error' => $servidor->ip . ' No se pudo autenticar'];
                    continue;
                }

                $output = $ssh->exec('free -b');
                $lines = preg_split('/\n/', trim($output));

                // Formato antiguo (CentOS 6): tiene línea extra "-/+ buffers/cache:"
                $formatoAntiguo = isset($lines[2]) && strpos(trim($lines[2]), '-/+') === 0;
                $lineaSwap = $formatoAntiguo ? 3 : 2;

                $mem = preg_split('/\s+/', trim($lines[1]));
                $swap = preg_split('/\s+/', trim($lines[$lineaSwap]));

                $ramTotal = (int) $mem[1];
                $ramUsed = $formatoAntiguo ? (int) preg_split('/\s+/', trim($lines[2]))[1] : (int) $mem[2];
                $ramPorcentaje = $ramTotal > 0 ? round($ramUsed / $ramTotal * 100, 1) : 0;

                $swapTotal = (int) $swap[1];
                $swapUsed = (int) $swap[2];
                $swapPorcentaje = $swapTotal > 0 ? round($swapUsed / $swapTotal * 100, 1) : 0;

                $resultado[$servidor->id] = [
                                                'ram' => ['total' => $ramTotal, 'used' => $ramUsed, 'porcentaje' => $ramPorcentaje],
                                                'swap' => ['total' => $swapTotal, 'used' => $swapUsed, 'porcentaje' => $swapPorcentaje],
                                            ];

            } catch (Exception $e) {
                $resultado[$servidor->id] = ['error' => $e->getMessage()];
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Stats obtenidos', $resultado));
    }
}
