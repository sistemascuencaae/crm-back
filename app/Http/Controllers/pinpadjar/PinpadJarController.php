<?php

namespace App\Http\Controllers\pinpadjar;

use App\Http\Controllers\Controller;
// RespuestaApi es un helper del proyecto para devolver JSON con formato consistente.
use App\Http\Resources\RespuestaApi;
// Servicio que orquesta el lanzamiento del JAR y la lectura de archivos.
use App\Servicios\PinpadJar\JarBridge;
use Illuminate\Http\Request;
// Str::uuid() de Laravel: genera un UUID v4 (RFC 4122), unico por sesion.
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * PinpadJarController — endpoints REST para el modo "Trama Builder JavaFX".
 *
 * Expone 4 acciones:
 *   GET    /pinpad/jar/diag           - diagnostico de paths/archivos
 *   POST   /pinpad/jar/abrir          - lanza la UI JavaFX
 *   GET    /pinpad/jar/leer/{id}      - polling: pending/ready/cancelled
 *   DELETE /pinpad/jar/limpiar/{id}   - cleanup tras consumir
 */
class PinpadJarController extends Controller
{
    public function __construct()
    {
        // Comentado por ahora; descomentar si se quiere proteger con JWT.
        // $this->middleware('auth:api');
    }

    /**
     * diag() - GET /pinpad/jar/diag
     *
     * Verifica que todos los paths necesarios existan, SIN lanzar Java.
     * Util como primera prueba antes de intentar abrir el JAR.
     */
    public function diag()
    {
        // Recolectamos info de cada componente requerido.
        $data = [
            'java_bin'             => config('pinpad.jar.java_bin'),
            // is_file() devuelve true si la ruta es un archivo regular.
            'java_bin_existe'      => is_file(config('pinpad.jar.java_bin')),
            'javafx_lib'           => config('pinpad.jar.javafx_lib'),
            // is_dir() para directorios (la SDK es un folder, no un file).
            'javafx_lib_existe'    => is_dir(config('pinpad.jar.javafx_lib')),
            'libreria_tramas_jar'  => config('pinpad.jar.libreria_tramas_jar'),
            'libreria_existe'      => is_file(config('pinpad.jar.libreria_tramas_jar')),
            'bridge_class'         => base_path('jars/TramaJarBridge.class'),
            'bridge_class_existe'  => is_file(base_path('jars/TramaJarBridge.class')),
            'storage_path'         => storage_path('app/pinpad-sessions'),
        ];
        // Si falta cualquier archivo, marcamos error.
        $todoOk = $data['java_bin_existe'] && $data['javafx_lib_existe']
               && $data['libreria_existe'] && $data['bridge_class_existe'];
        $estado = $todoOk ? 'success' : 'error';
        $msg    = $todoOk ? 'Diagnostico OK' : 'Faltan archivos o paths incorrectos';
        return response()->json(RespuestaApi::returnResultado($estado, $msg, $data));
    }

    /**
     * abrir() - POST /pinpad/jar/abrir
     *
     * 1) Genera un UUID unico de sesion
     * 2) Lanza Java en background (abre la ventana en pantalla del cajero)
     * 3) Limpia sesiones huerfanas viejas (housekeeping)
     * 4) Devuelve el session_id para que el frontend haga polling
     */
    public function abrir(Request $req)
    {
        try {
            // (string) cast porque Str::uuid() devuelve un objeto Stringable.
            $sessionId = (string) Str::uuid();

            $bridge = new JarBridge();
            // Lanza Java. Esta llamada NO espera a que el cajero termine
            // (el lanzamiento es "fire and forget" via popen).
            $result = $bridge->abrir($sessionId);

            // Limpieza oportunista de sesiones huerfanas (>60 min sin actividad).
            // Lo hacemos aqui para no necesitar un cron job.
            $bridge->limpiarAntiguas(60);

            return response()->json(RespuestaApi::returnResultado('success', 'JAR lanzado', $result));
        } catch (\Throwable $e) {
            // Throwable atrapa tanto Exception como Error (errores fatales).
            // Logueamos al storage/logs/laravel.log para debug post-mortem.
            Log::error('Pinpad/jar/abrir: ' . $e->getMessage());
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), null));
        }
    }

    /**
     * leer() - GET /pinpad/jar/leer/{sessionId}
     *
     * Polling endpoint. El frontend lo llama cada 1.5s.
     * Devuelve uno de tres estados segun los archivos en disco:
     *   - pending   : Java sigue ejecutandose, sin trama aun
     *   - ready     : trama generada (vino el .txt)
     *   - cancelled : usuario cerro la ventana sin generar (vino el .done)
     */
    public function leer($sessionId)
    {
        try {
            $data = (new JarBridge())->leer($sessionId);
            return response()->json(RespuestaApi::returnResultado('success', 'Estado sesion', $data));
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), null));
        }
    }

    /**
     * limpiar() - DELETE /pinpad/jar/limpiar/{sessionId}
     *
     * El frontend lo llama tras consumir la trama, para que no queden
     * archivos huerfanos en disco.
     */
    public function limpiar($sessionId)
    {
        try {
            (new JarBridge())->limpiar($sessionId);
            return response()->json(RespuestaApi::returnResultado('success', 'Limpiado', null));
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), null));
        }
    }
}
