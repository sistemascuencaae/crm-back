<?php

namespace App\Models\activos;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dacta extends Model
{
    use HasFactory;

    protected $table = 'crm.dacta';

    protected $fillable = [
        "id_cacta",
        "id_activo",
        "secuencia",
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

    // Relación con Cacta (cabecera)
    public function cacta()
    {
        return $this->belongsTo(Cacta::class, 'id_cacta', 'id');
    }

    // Relación con Activo
    public function activo()
    {
        return $this->belongsTo(Activo::class, 'id_activo', 'id');
    }
}
