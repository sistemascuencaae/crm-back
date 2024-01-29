<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendMsgEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    public $uniqd;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data,$uniqd)
    {
        $this->data = $data;
        $this->uniqd = $uniqd;
    }

    public function broadcastWith(): array
    {
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

       return new PrivateChannel('chat.'. $this->uniqd);
    }
}
