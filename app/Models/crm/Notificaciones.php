<?php

namespace App\Models\crm;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificaciones extends Model
{
    use HasFactory;

    protected $table = 'crm.notificaciones';

    protected $fillable = [
        "user_id",
        "titulo",
        "descripcion",
        "estado",
        "orden",
        "color",
        "caso_id",
        "tipo",
        "usuario_accion",
        "tab_id",
        "usuario_destino_id",
    ];

    public function caso()
    {
        return $this->belongsTo(Caso::class,"caso_id","id");
    }
    public function tablero()
    {
        return $this->belongsTo(Tablero::class,"tab_id","id");
    }

    public function user_destino()
    {
        return $this->belongsTo(User::class,"usuario_destino_id","id");
    }










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
}
