<?php

namespace App\Events;

use App\Http\Controllers\chat\ChatController;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnviarMensajeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    public $converId;
    public $tipoConver;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data, $converId, $tipoConver)
    {
        $this->data = $data;
        $this->converId = $converId;
        $this->tipoConver = $tipoConver;
    }

    public function broadcastWith(): array
    {
        // $chatController = new ChatController();
        // $data = $chatController->getMensajes($this->converId, $this->tipoConver);
        return [
            'data' => $this->data
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('conversacion.' . $this->converId.'.'. $this->tipoConver);
    }
}
