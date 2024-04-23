<?php

namespace App\Models\chat;

use App\Models\crm\Archivo;
use App\Models\crm\Galeria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMensajeArchivo extends Model
{
    use HasFactory;
    protected $table = 'crm.chat_mensaje_archivo';

    protected $fillable = [
        "id",
        "mensaje_id",
        "archivo_id",
    ];

    public function archivos()
    {
        return $this->belongsTo(Archivo::class, "archivo_id");
    }


}
