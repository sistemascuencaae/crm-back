<?php

namespace App\Models\chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMiembrosGrupo extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_miembros_grupo';

    protected $fillable = [
        "user_id",
        "chatgrupo_id"
    ];

    public function userMiembro()
    {
        return $this->hasMany(User::class, "user_id", "id");
    }
    public function grupo()
    {
        return $this->hasMany(ChatGrupos::class, "chatgrupo_id", "id");
    }

}
