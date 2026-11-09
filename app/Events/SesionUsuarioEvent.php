<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// 08-nov: aviso al usuario de que le cambiaron la agencia o el perfil. Va SOLO a su canal privado usuario.{id}, el que el header
// ya escucha en todas las pantallas. No lleva nombres ni datos: el front, al recibirlo, pide su sesión (GET /api/access/sesion),
// muestra el aviso con Entendido y recarga la página. Lo dispara el modelo User (booted) después del commit.
class SesionUsuarioEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;

    // 'agencia' y/o 'perfil'
    public $cambios;

    public function __construct(int $userId, array $cambios)
    {
        $this->userId = $userId;
        $this->cambios = array_values($cambios);
    }

    // Manda el aviso después del commit (sin transacción abierta, al momento): así el front nunca pide su sesión antes de que el
    // cambio quede guardado y un rollback no avisa nada. Si el servidor de WebSockets no responde, solo se anota en el log: el
    // guardado nunca falla por el aviso. Lo usan el modelo User (Editar usuario, cajeras de Dynamo) y Cambio de agencia
    // (CambioAgenciaController, que guarda con crm.fn_vendedor_actualizar_agencia y no pasa por el modelo).
    public static function avisar(int $userId, array $cambios): void
    {
        DB::afterCommit(function () use ($userId, $cambios) {
            try {
                broadcast(new static($userId, $cambios));
            } catch (\Throwable $e) {
                Log::warning('SesionUsuarioEvent usuario ' . $userId . ': ' . $e->getMessage());
            }
        });
    }

    public function broadcastWith(): array
    {
        return ['cambios' => $this->cambios];
    }

    public function broadcastOn()
    {
        return new PrivateChannel('usuario.' . $this->userId);
    }
}
