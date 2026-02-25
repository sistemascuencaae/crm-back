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

            return response()->json(
                RespuestaApi::returnResultado('success', 'Servidores obtenidos', $servidores)
            );
        } catch (Exception $e) {
            return response()->json(
                RespuestaApi::returnResultado('error', 'Error al obtener servidores', $e->getMessage())
            );
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

            $output = $ssh->exec('bash ' . $servidor->archivo . ' 2>&1');

            $log->logInfo(ServidorController::class, 'Script ejecutado en ' . $host . ' [id:' . $servidorId . ']');

            return response()->json(RespuestaApi::returnResultado('success', 'Servidor reiniciado con éxito ' . $host, null));
        } catch (Exception $e) {
            $log->logError(ServidorController::class, 'Error al ejecutar script en ' . $host, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al ejecutar el script en ' . $host, $e->getMessage()));
        }
    }
}
