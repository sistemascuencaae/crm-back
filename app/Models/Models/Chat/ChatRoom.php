<?php

namespace App\Models\Chat;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatRoom extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "first_user",//14
        "second_user",
        "chat_group_id",
        "last_at",
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

    public function FirstUser()
    {
        return $this->belongsTo(User::class,"first_user");
    }

    public function SecondUser()
    {
        return $this->belongsTo(User::class,"second_user");
    }

    public function ChatGroup()
    {
        return $this->belongsTo(ChatGroup::class,"chat_group_id");
    }

    public function Chats()
    {
        return $this->hasMany(Chat::class,"chat_room_id");
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
