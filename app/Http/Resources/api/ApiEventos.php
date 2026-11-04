<?php

namespace App\Http\Resources\api;

use App\Events\ApiColeccionEvent;
use App\Events\ApiUsuarioEvent;
use App\Http\Resources\crm\Funciones;
use Throwable;

// 30-oct: dispara los eventos de tiempo real del API Client SIEMPRE dentro de try/catch: si el servidor de websockets no
// está arriba, broadcast() lanza y el módulo tiene que seguir igual (el front recarga a mano).
class ApiEventos
{
    // Canal api.coleccion.{id}: tipo 'coleccion' | 'requests' (con request_id) | 'usuarios' | 'entorno' | 'eliminada'.
    public static function coleccion(?int $coleccionId, string $tipo, ?int $origenUsuarioId, ?int $requestId = null): void
    {
        if (!$coleccionId) {
            return;
        }
        try {
            broadcast(new ApiColeccionEvent($coleccionId, $tipo, $origenUsuarioId, $requestId));
        } catch (Throwable $e) {
            self::anotar('ApiColeccionEvent ' . $tipo . ' colección ' . $coleccionId, $e);
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
