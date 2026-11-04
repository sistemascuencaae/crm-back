<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 30-oct: API Client. Algo cambió en una colección: canal privado api.coleccion.{id} (lo escuchan el dueño y los usuarios
// agregados). tipo: 'coleccion' (nombre/color), 'requests' (request creado/editado/borrado, con request_id), 'usuarios'
// (cambió la lista de usuarios), 'entorno' (entorno de la colección) o 'eliminada'.
class ApiColeccionEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $coleccionId;
    public string $tipo;
    public ?int $origenUsuarioId;
    public ?int $requestId;

    public function __construct(int $coleccionId, string $tipo, ?int $origenUsuarioId, ?int $requestId = null)
    {
        $this->coleccionId = $coleccionId;
        $this->tipo = $tipo;
        $this->origenUsuarioId = $origenUsuarioId;
        $this->requestId = $requestId;
    }

    public function broadcastWith(): array
    {
        return [
            'coleccion_id' => $this->coleccionId,
            'tipo' => $this->tipo,
            'origen_usuario_id' => $this->origenUsuarioId,
            'request_id' => $this->requestId,
        ];
    }

    public function broadcastOn()
    {
        return new PrivateChannel('api.coleccion.' . $this->coleccionId);
    }
}
