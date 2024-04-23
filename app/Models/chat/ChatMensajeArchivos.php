<?php

namespace App\Models\chat;

use App\Models\crm\Archivo;
use App\Models\crm\Galeria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMensajeArchivos extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_mensaje_archivos';

    protected $fillable = [
        "id",
        "mensaje_id",
        "galeria_id",
        "archivo_id",
    ];

    public function img()
    {
        return $this->belongsTo(Galeria::class, "galeria_id");
    }
    public function archivos()
    {
        return $this->belongsTo(Archivo::class, "archivo_id");
    }


}
