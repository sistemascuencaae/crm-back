<?php

namespace App\Models\chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversaciones extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_conversaciones';

    protected $fillable = [
        "id",
        "user_uno_id",
        "user_dos_id",
        "uniqd"
    ];

    public function userUno()
    {
        return $this->belongsTo(User::class, "user_uno_id", "id");
    }
    public function userDos()
    {
        return $this->belongsTo(User::class, "user_dos_id", "id");
    }
    public function mensajesNormal()
    {
        return $this->hasMany(ChatMensajes::class, "chatconve_id", "id");
    }


}
