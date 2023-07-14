<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableroEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data)
    {

        $this->data = $data;
    }


    public function broadcastWith(): array
    {
        // echo('aaaaaa--------------------------------');
        // echo(json_encode($this->data));
        // echo('aaaaaa--------------------------------');
        return [
            'data' => $this->data[0]
        ];
    }




    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    // public function broadcastOn()
    // {
    //     return new Channel('comentarios');
    // }

    public function broadcastOn()
    {
        //echo('estamos en el lugar'.$this->tablero_id);
        return new PrivateChannel('tablero.'.$this->data[0]->tablero_id );
    }
}
