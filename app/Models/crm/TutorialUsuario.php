<?php

namespace App\Models\crm;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorialUsuario extends Model
{
    use HasFactory;

    protected $table = 'crm.tutorial_usuario';

    protected $fillable = [
        "archivo_id",
        "galeria_id",
        "user_id",
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["updated_at"] = Carbon::now();
    }

    public function archivos()
    {
        return $this->belongsTo(Archivo::class, "archivo_id", "id");
    }

    public function galerias()
    {
        return $this->belongsTo(Galeria::class, "galeria_id", "id");
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }
}
