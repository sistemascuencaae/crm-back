<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

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
            'data' => $this->data
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


        $tableroId = DB::select('SELECT ta.id FROM crm.fase fa
        INNER JOIN crm.tablero ta on ta.id = fa.tab_id
        where fa.id = ' . $this->data->fas_id.' limit 1');

        if(isset($this->data->tablero_id)){
            return new PrivateChannel('tablero.'.$this->data->tablero_id );
        }else{

            return new PrivateChannel('tablero.'.$tableroId[0]->id);
        }







    }
}
