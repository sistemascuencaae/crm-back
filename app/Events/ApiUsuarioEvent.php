<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 30-oct: API Client. Aviso a UN usuario por su canal privado App.User.{id} (ya autorizado en channels.php):
// 'compartida' cuando lo agregan a una colección, 'eliminada' cuando lo quitan o borran la colección.
class ApiUsuarioEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public int $coleccionId;
    public string $tipo;
    public ?int $origenUsuarioId;

    public function __construct(int $userId, int $coleccionId, string $tipo, ?int $origenUsuarioId)
    {
        $this->userId = $userId;
        $this->coleccionId = $coleccionId;
        $this->tipo = $tipo;
        $this->origenUsuarioId = $origenUsuarioId;
    }

    public function broadcastWith(): array
    {
        return [
            'coleccion_id' => $this->coleccionId,
            'tipo' => $this->tipo,
            'origen_usuario_id' => $this->origenUsuarioId,
        ];
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.User.' . $this->userId);
    }
}
