<?php

namespace App\Http\Resources\api;

use App\Events\ApiColeccionEvent;
use App\Events\ApiUsuarioEvent;
use App\Http\Resources\crm\Funciones;
use Illuminate\Support\Facades\DB;
use Throwable;

// 30-oct: dispara los eventos de tiempo real del API Client SIEMPRE dentro de try/catch: si el servidor de websockets no
// está arriba, broadcast() lanza y el módulo tiene que seguir igual (el front recarga a mano).
class ApiEventos
{
    // Canal api.coleccion.{id}: tipo 'coleccion' | 'requests' (con request_id) | 'usuarios' | 'entorno' | 'eliminada'.
    // 07-nov: va al canal App.User.{id} del dueño y de cada agregado. $destinatarios: los leídos antes de borrar la
    // colección; sin ellos se leen ahora.
    public static function coleccion(?int $coleccionId, string $tipo, ?int $origenUsuarioId, ?int $requestId = null, ?array $destinatarios = null): void
    {
        if (!$coleccionId) {
            return;
        }
        try {
            $destinatarios = $destinatarios ?? self::destinatarios($coleccionId);
            if (!$destinatarios) {
                return;
            }
            broadcast(new ApiColeccionEvent($coleccionId, $tipo, $origenUsuarioId, $requestId, $destinatarios));
        } catch (Throwable $e) {
            self::anotar('ApiColeccionEvent ' . $tipo . ' colección ' . $coleccionId, $e);
        }
    }

    // 07-nov: dueño y usuarios agregados de la colección. Si falla, ninguno: el aviso no rompe el guardado.
    public static function destinatarios(int $coleccionId): array
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_api_coleccion_destinatarios_listar(?::bigint) AS ids', [$coleccionId]);

            return array_map('intval', json_decode($fila->ids ?? '[]', true) ?: []);
        } catch (Throwable $e) {
            self::anotar('ApiColeccionEvent (destinatarios) colección ' . $coleccionId, $e);

            return [];
        }
    }

    // Canal App.User.{userId}: tipo 'compartida' | 'eliminada'.
    public static function usuario(?int $userId, ?int $coleccionId, string $tipo, ?int $origenUsuarioId): void
    {
        if (!$userId || !$coleccionId) {
            return;
        }
        try {
            broadcast(new ApiUsuarioEvent($userId, $coleccionId, $tipo, $origenUsuarioId));
        } catch (Throwable $e) {
            self::anotar('ApiUsuarioEvent ' . $tipo . ' usuario ' . $userId . ' colección ' . $coleccionId, $e);
        }
    }

    // Solo una línea informativa (sin websockets pasaría en cada guardado: no es un error del módulo).
    private static function anotar(string $que, Throwable $e): void
    {
        try {
            (new Funciones())->logInfo(ApiEventos::class, 'No se pudo emitir ' . $que . ': ' . $e->getMessage());
        } catch (Throwable $ignorado) {
            // ni el log debe romper la respuesta
        }
    }
}
