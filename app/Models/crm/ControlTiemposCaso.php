<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ControlTiemposCaso extends Model
{
    use HasFactory;

    protected $table = 'crm.control_tiempos_caso';
    protected $fillable = [
        "caso_id",
        "est_caso_id",
        "tiempo_cambio",
        "fase",
        "fase_id",
        "tipo",
        "user_id",
        "usuario",
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

}