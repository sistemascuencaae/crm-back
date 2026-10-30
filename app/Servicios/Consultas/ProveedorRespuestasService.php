<?php

namespace App\Servicios\Consultas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

// 29-oct (pedido del usuario): crm.proveedor_respuestas, una fila por CADA llamada real a un proveedor de consultas
// (GaranCheck, Ecuador Legal, SRI; mañana Yupana), buena o mala, para cuadrar al final del mes. La respuesta cruda solo
// en los errores. Ver PLANES/GARANCHECK/produccion_garancheck_consulta_mes.sql.
// Nunca rompe la consulta: si no se puede guardar, solo se anota en el log de Laravel. Cada escritura va en su propia
// transacción (un SAVEPOINT si ya hay una abierta), así un fallo aquí no deja abortada la transacción del que llama.
class ProveedorRespuestasService
{
    // Resultados: los mismos que valida crm.fn_proveedor_respuestas_registrar.
    public const OK = 'OK';
    public const SIN_IDENTIDAD = 'SIN_IDENTIDAD';
    public const ERROR_PROVEEDOR = 'ERROR_PROVEEDOR';
    public const ERROR_HTTP = 'ERROR_HTTP';
    public const SIN_RESPUESTA = 'SIN_RESPUESTA';
    public const RESPUESTA_INVALIDA = 'RESPUESTA_INVALIDA';
    public const SIN_CONFIGURACION = 'SIN_CONFIGURACION';

    // Desde dónde se consultó.
    public const CANAL_NUEVO_CASO = 'NUEVO_CASO';
    public const CANAL_FORMULARIO_STS = 'FORMULARIO_STS';
    public const CANAL_API_STS = 'API_STS';
    public const CANAL_CRM = 'CRM';
    public const CANAL_PAGINA_WEB = 'PAGINA_WEB';

    // Un cuerpo que no es JSON (HTML de un error, la página de Ecuador Legal) se guarda recortado.
    private const MAX_TEXTO = 20000;

    // $fila: proveedor, canal, identificacion, resultado y lo que se sepa (cli_id, usu_id, corredor, http_status,
    // mensaje, duracion_ms, respuesta, consulta_id). Devuelve el id de la fila, o null si no se pudo guardar.
    public static function registrar(array $fila): ?int
    {
        try {
            $fila['canal'] = ($fila['canal'] ?? '') ?: 'SIN_CANAL';
            if (array_key_exists('respuesta', $fila)) {
                $fila['respuesta'] = self::respuestaCruda($fila['respuesta']);
            }
            if (isset($fila['mensaje'])) {
                $fila['mensaje'] = mb_substr((string) $fila['mensaje'], 0, self::MAX_TEXTO);
            }

            $resultado = DB::transaction(function () use ($fila) {
                $r = DB::selectOne('SELECT crm.fn_proveedor_respuestas_registrar(?::jsonb) AS resultado', [json_encode($fila, JSON_UNESCAPED_UNICODE)]);

                return json_decode($r->resultado, true) ?: [];
            });

            if (empty($resultado['success'])) {
                Log::warning('No se guardó la respuesta del proveedor: ' . ($resultado['message'] ?? 'sin mensaje'));

                return null;
            }

            return (int) $resultado['id'];
        } catch (Throwable $e) {
            Log::warning('No se guardó la respuesta del proveedor: ' . $e->getMessage());

            return null;
        }
    }

    // Completa una fila ya registrada: solo las llaves de $cambios (resultado, mensaje, respuesta, cli_id, consulta_id).
    public static function modificar(?int $id, array $cambios): void
    {
        if ($id === null) {
            return;
        }

        try {
            if (array_key_exists('respuesta', $cambios)) {
                $cambios['respuesta'] = self::respuestaCruda($cambios['respuesta']);
            }
            if (isset($cambios['mensaje'])) {
                $cambios['mensaje'] = mb_substr((string) $cambios['mensaje'], 0, self::MAX_TEXTO);
            }

            $resultado = DB::transaction(function () use ($id, $cambios) {
                $r = DB::selectOne('SELECT crm.fn_proveedor_respuestas_modificar(?::jsonb) AS resultado', [json_encode(['id' => $id] + $cambios, JSON_UNESCAPED_UNICODE)]);

                return json_decode($r->resultado, true) ?: [];
            });

            if (empty($resultado['success'])) {
                Log::warning('No se actualizó la respuesta del proveedor ' . $id . ': ' . ($resultado['message'] ?? 'sin mensaje'));
            }
        } catch (Throwable $e) {
            Log::warning('No se actualizó la respuesta del proveedor ' . $id . ': ' . $e->getMessage());
        }
    }

    // El cuerpo como lo guarda la columna jsonb: un arreglo tal cual; un texto JSON decodificado; cualquier otro texto,
    // recortado en {"texto": ...}; vacío = null.
    public static function respuestaCruda($cuerpo): ?array
    {
        if (is_array($cuerpo)) {
            return $cuerpo;
        }
        if (!is_scalar($cuerpo) || trim((string) $cuerpo) === '') {
            return null;
        }

        $texto = (string) $cuerpo;
        $json = json_decode($texto, true);
        if (is_array($json)) {
            return $json;
        }

        return ['texto' => mb_substr($texto, 0, self::MAX_TEXTO)];
    }

    // Milisegundos desde $inicio (microtime(true)).
    public static function milisegundos(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
