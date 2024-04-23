<?php

namespace App\Models\chat;

use App\Models\crm\Archivo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMensajes extends Model
{
    use HasFactory;
    use SoftDeletes;
    public $timestamps = true;
    protected $table = 'crm.chat_mensajes';

    protected $fillable = [
        "id",
        "chatconve_id",
        "chatgrupo_id",
        "user_id",
        "mensaje",
        "galeria_id",
        "archivo_id",
        "read_at",
    ];
    public function user()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }
    public function mensajeConversacion()
    {
        return $this->belongsTo(ChatConversaciones::class, "chatconve_id", "id");
    }
    public function grupo()
    {
        return $this->belongsTo(ChatGrupos::class, "chatgrupo_id", "id");
    }

    public function archivosImg()
    {
        return $this->hasMany(ChatMensajeArchivos::class, "mensaje_id");
    }

    public function archivosFile()
    {
        return $this->hasMany(ChatMensajeArchivo::class, "mensaje_id");
    }

    public function archivo()
    {
        return $this->belongsTo(Archivo::class, "archivo_id");
    }



    public function delete()
    {
        $this->timestamps = false; // Deshabilitar marcas de tiempo
        $deleted = parent::delete(); // Eliminar el registro
        $this->timestamps = true; // Volver a habilitar marcas de tiempo
        return $deleted;
    }

}
