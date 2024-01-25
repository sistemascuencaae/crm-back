<?php

namespace App\Models\chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_rooms';
    protected $fillable = ['primer_user', 'segundo_user', 'chat_group_id', 'uniqd'];

    public function primerUser()
    {
        return $this->belongsTo(User::class, 'primer_user');
    }

    public function segundoUser()
    {
        return $this->belongsTo(User::class, 'segundo_user');
    }

    public function chatGroup()
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class, 'chat_room_id');
    }
}
