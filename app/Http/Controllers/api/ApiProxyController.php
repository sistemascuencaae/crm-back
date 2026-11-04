<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\api\ApiProxyHelper;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

// 30-oct: proxy del API Client. POST api-client/ejecutar recibe el PayloadEjecutar (JSON) o multipart (meta + archivos[])
// y responde siempre HTTP 200 con la envoltura: success → RespuestaProxy; error → status 'error' y data ErrorProxy.
// 30-oct F3: GET api-client/descargar/{token} baja una respuesta grande guardada como temporal (un solo uso, 1 h).
class ApiProxyController extends Controller
{
    public function ejecutar(Request $request)
    {
        try {
            $resultado = (new ApiProxyHelper())->ejecutar($request);

            return response()->json(RespuestaApi::returnResultado($resultado['status'], $resultado['message'], $resultado['data']));
        } catch (Throwable $e) {
            (new Funciones())->logError(ApiProxyController::class, 'Error inesperado del proxy', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al ejecutar la petición', [
                'error' => true,
                'error_tipo' => 'proxy',
                'mensaje' => $e->getMessage(),
                'tiempo_ms' => 0,
                'historial_id' => null,
            ]));
        }
    }

    // Deja la marca que el callback "progress" del proxy revisa para cortar la llamada en curso (uid de la ejecución).
    public function cancelar(Request $request, $uid)
    {
        try {
            $uid = preg_replace('/[^\w\-]/', '', (string) $uid);
            if ($uid === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'Identificador de ejecución inválido', null));
            }

            Cache::put(ApiProxyHelper::claveCancelar($uid), 1, 600);

            return response()->json(RespuestaApi::returnResultado('success', 'Cancelación solicitada', ['uid' => $uid]));
        } catch (Throwable $e) {
            (new Funciones())->logError(ApiProxyController::class, 'Error al cancelar la ejecución ' . $uid, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cancelar la ejecución', $e->getMessage()));
        }
    }

    // 30-oct F3: baja una respuesta grande que el proxy dejó como temporal (storage/app/api-client/tmp/<token>.bin) y la
    // borra al terminar: el token es de un solo uso y vive 1 h. La ruta va fuera del grupo jwt.auth porque el front la
    // abre con <a download> (sin header Authorization): la credencial es el token. Si no existe o ya se bajó → 404 JSON.
    public function descargar(Request $request, $token)
    {
        $token = strtolower(trim((string) $token));
        try {
            if (!ApiProxyHelper::tokenValido($token)) {
                return $this->descargaVencida();
            }
            $base = ApiProxyHelper::rutaTemporal($token);
            if (!is_file($base . '.bin')) {
                return $this->descargaVencida();
            }
            $meta = is_file($base . '.json') ? json_decode((string) @file_get_contents($base . '.json'), true) : null;
            $nombre = $this->nombreSeguro(is_array($meta) ? (string) ($meta['filename'] ?? '') : '');
            @unlink($base . '.json');

            (new Funciones())->logInfo(ApiProxyController::class, 'Descarga de la respuesta grande ' . $token . ' (' . $nombre . ')');

            return response()->download($base . '.bin', $nombre, [
                'Content-Type' => 'application/octet-stream',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            (new Funciones())->logError(ApiProxyController::class, 'Error al descargar la respuesta grande ' . $token, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo descargar la respuesta', $e->getMessage()), 500);
        }
    }

    private function descargaVencida()
    {
        return response()->json(RespuestaApi::returnResultado('error', 'La descarga venció', null), 404);
    }

    // Solo un nombre de archivo (sin rutas, comillas, % ni caracteres de control, que Content-Disposition no admite).
    private function nombreSeguro(string $nombre): string
    {
        $nombre = trim((string) preg_replace('/[\x00-\x1f\x7f"%\/\\\\]+/', '_', trim($nombre)), ' .');

        return $nombre !== '' ? $nombre : 'respuesta.bin';
    }
}
