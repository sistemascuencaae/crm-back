<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de que se publico un anuncio nuevo.
 *
 * OJO: a proposito NO lleva datos del anuncio. El canal es publico, asi que
 * cualquier navegador conectado recibe este evento; si aqui viajara el titulo
 * o las imagenes, un anuncio dirigido solo a Gerencia se podria leer desde la
 * consola de cualquier usuario.
 *
 * Al recibirlo, cada cliente vuelve a consultar listAnunciosUsuario, que es
 * quien filtra por departamento, perfil y usuario en el servidor. Asi la
 * segmentacion nunca depende del navegador, y basta UN canal para todos en
 * vez de uno privado por usuario.
 */
class AnuncioEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastWith(): array
    {
        return [
            'hay_nuevos' => true,
        ];
    }

    public function broadcastOn()
    {
        return new Channel('anuncios.crm');
    }
}
