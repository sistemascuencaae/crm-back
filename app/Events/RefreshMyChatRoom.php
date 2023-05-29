<?php

namespace App\Events;

use App\Models\Chat\ChatRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use App\Http\Resources\Chat\ChatGResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class RefreshMyChatRoom implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $to_user_id;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($to_user_id)
    {
        $this->to_user_id = $to_user_id;
    }

    public function broadcastWith()
    {
        $chatrooms = ChatRoom::where("first_user", $this->to_user_id)->orWhere("second_user", $this->to_user_id)
                               ->orderBy("last_at","desc")
                               ->get();

        date_default_timezone_set("America/Lima");
        return [
            "chatrooms" => $chatrooms->map(function($item){
                return [
                    "friend_first" => $item->first_user != $this->to_user_id ?
                    [
                        "id" => $item->FirstUser->id,
                        "full_name" => $item->FirstUser->name.' '.$item->FirstUser->surname,
                        "avatar" => $item->FirstUser->avatar ? env("APP_URL")."storage/".$item->FirstUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
                    ] : NULL,
                    "friend_second" => $item->second_user ?
                        $item->second_user != $this->to_user_id ?
                        [
                            "id" => $item->SecondUser->id,
                            "full_name" => $item->SecondUser->name.' '.$item->SecondUser->surname,
                            "avatar" => $item->SecondUser->avatar ? env("APP_URL")."storage/".$item->SecondUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
                        ] : NULL
                    : NULL,
                    "group_chat" => $item->chat_group_id ? [
                        "id" => $item->ChatGroup->id,
                        "name" => $item->ChatGroup->name,
                        "avatar" => NULL,

                        "last_message" => $item->ChatGroup->last_message,
                        "last_message_is_my" => $item->ChatGroup->last_message_user ?  $item->ChatGroup->last_message_user === $this->to_user_id : NULL,
                        "last_time" => $item->ChatGroup->last_time_created_at,
                        "count_message" => $item->ChatGroup->getCountMessages($this->to_user_id),
                    ] : NULL,
                    "uniqd" => $item->uniqd,
                    "is_active" => false,
                    "last_message" => $item->last_message,
                    "last_message_is_my" => $item->last_message_user ?  $item->last_message_user === $this->to_user_id : NULL,
                    "last_time" => $item->last_time_created_at,
                    "count_message" => $item->getCountMessages($this->to_user_id),
                ];
            }),
        ];
    }
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('chat.refresh.room.'.$this->to_user_id);
    }
}
