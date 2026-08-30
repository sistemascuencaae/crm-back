<?php

namespace App\Models\configuracion;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioCHorario extends Model
{
    use HasFactory;

    protected $table = 'crm.usuario_chorario';
    protected $fillable = [
        "user_id",
        "chorario_id",
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

    public function chorario()
    {
        return $this->belongsTo(CHorario::class, "chorario_id","id");
    }
}
