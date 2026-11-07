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
// 07-nov: un solo canal por usuario: va al canal App.User.{id} de cada destinatario (dueño y agregados) en un solo envío.
class ApiColeccionEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $coleccionId;
    public string $tipo;
    public ?int $origenUsuarioId;
    public ?int $requestId;
    public array $destinatarios;

    public function __construct(int $coleccionId, string $tipo, ?int $origenUsuarioId, ?int $requestId = null, array $destinatarios = [])
    {
        $this->coleccionId = $coleccionId;
        $this->tipo = $tipo;
        $this->origenUsuarioId = $origenUsuarioId;
        $this->requestId = $requestId;
        $this->destinatarios = array_values(array_unique(array_map('intval', $destinatarios)));
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
        // DESACTIVADO 07-nov: un canal por colección = una llamada a broadcasting/auth por cada colección abierta
        // return new PrivateChannel('api.coleccion.' . $this->coleccionId);
        return array_map(fn (int $usuarioId) => new PrivateChannel('App.User.' . $usuarioId), $this->destinatarios);
    }
}
