<?php

namespace App\Models\Chat;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chat extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "from_user_id",
        "chat_room_id",
        "chat_group_id",
        "message",
        "chat_file_id",
        "read_at"
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

    public function FromUser()
    {
       return $this->belongsTo(User::class,"from_user_id");
    }

    public function ChatRoom()
    {
       return $this->belongsTo(ChatRoom::class,"chat_room_id");
    }

    public function ChatGroup()
    {
       return $this->belongsTo(ChatGroup::class,"chat_group_id");
    }

    public function ChatFile()
    {
       return $this->belongsTo(ChatFile::class,"chat_file_id");
    }
}
