<?php

namespace App\Models\chat;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroup extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_groups';
    protected $fillable = ['nombre', 'uniqd'];

    public function chatRoom()
    {
        return $this->hasMany(ChatRoom::class, 'chat_group_id');
    }

    public function chat(){
        return $this->hasMany(Chat::class, 'chat_group_id');
    }





}
