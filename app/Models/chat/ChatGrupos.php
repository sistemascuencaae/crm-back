<?php

namespace App\Models\chat;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGrupos extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_grupos';

    protected $fillable = [
        "id",
        "nombre_grupo",
    ];

    public function mensajesGrupal()
    {
        return $this->hasMany(ChatMensajes::class, "chatgrupo_id", "id");
    }
}
