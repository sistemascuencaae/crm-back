<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendMessageChat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $chat;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($chat)
    {
        $this->chat = $chat;
    }

    public function broadcastWith()
    {
        // return [
        //     "id" => $this->chat->id,
        //     "sender" => [
        //         "id" => $this->chat->FromUser->id,
        //         "full_name" => $this->chat->FromUser->name.' '.$this->chat->FromUser->surnme,
        //         "avatar" => $this->chat->FromUser->avatar ? env("APP_URL")."storage/".$this->chat->FromUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
        //     ],
        //     "message" => $this->chat->message,
        //     // "filw"
        //     "file" => $this->chat->ChatFile ? [
        //         "id" => $this->chat->ChatFile->id,
        //         "file_names" => $this->chat->ChatFile->file_names,
        //         "resolution" => $this->chat->ChatFile->resolution,
        //         "type" => $this->chat->ChatFile->type,
        //         "size" => $this->chat->ChatFile->size,
        //         "file" => env("APP_URL")."storage/".$this->chat->ChatFile->file,
        //         "uniqd" => $this->chat->ChatFile->uniqd,
        //         "created_at" =>  $this->chat->ChatFile->created_at->format("Y-m-d h:i A"),
        //     ]: null,
        //     "read_at" => $this->chat->read_at,
        //     "time" => $this->chat->created_at->diffForHumans(),
        //     "created_at" => $this->chat->created_at,
        // ];

        echo("ingresamos aqui: ".json_encode($this->chat));
        return [
            'data' => $this->chat,
            'state' => 200
        ];


    }
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('chat.room.'.$this->chat->uniqd);
    }
}
