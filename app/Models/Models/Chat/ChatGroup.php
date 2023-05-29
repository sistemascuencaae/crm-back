<?php

namespace App\Models\Chat;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatGroup extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "name",
        "uniqd"
    ];

    public function setCreatedAtAtribute($value)
    {
        date_default_timezone_set("America/Lima");
        $this->attributes["created_at"] = Carbon::now();
    }

    public function setUpdateAtAtribute($value)
    {
        date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"] = Carbon::now();
    }

    public function Chats()
    {
        return $this->hasMany(Chat::class,"chat_group_id");
    }

    public function ChatRooms()
    {
        return $this->hasMany(ChatRoom::class,"chat_group_id");
    }

    public function getLastMessageAttribute()
    {
        $chat = $this->Chats->sortByDesc("id")->first();

        return $chat ?
                $chat->message ?  $chat->message : 'Archivo Enviado'
               : NULL;
    }

    public function getLastMessageUserAttribute()
    {
        $chat = $this->Chats->sortByDesc("id")->first();

        return $chat ?
                $chat->from_user_id
               : NULL;
    }

    public function getLastTimeCreatedAtAttribute()
    {
        $chat = $this->Chats->sortByDesc("id")->first();

        return $chat ?
                $chat->created_at->diffForHumans()
               : NULL;
    }

    public function getCountMessages($user)
    {
        return $this->Chats->where("from_user_id","<>",$user)->where("read_at",NULL)->count();
    }
}
