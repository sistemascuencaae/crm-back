<?php

namespace App\Models\crm;

use App\Models\User;
use App\Models\crm\Entidad;
use App\Models\crm\AVResumenCaso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Caso extends Model
{
    // use AuditableTrait;

    use HasFactory;

    protected $table = 'crm.caso';

    protected $fillable = [
        "fas_id",
        "nombre",
        "descripcion",
        "estado",
        "prioridad",
        "orden",
        "ent_id",
        "user_id",
        "fecha_vencimiento",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }

    public function entidad()
    {
        return $this->belongsTo(Entidad::class, "ent_id");
    }
    public function resumen()
    {
        return $this->belongsTo(AVResumenCaso::class, "id");
    }
    public function Tareas()
    {
        return $this->hasMany(Tareas::class, "caso_id");
    }
    public function Actividad()
    {
        return $this->hasMany(Actividad::class, "caso_id");
    }
    public function Etiqueta()
    {
        return $this->hasMany(Etiqueta::class, "caso_id");
    }

    public function Galeria()
    {
        return $this->hasMany(Galeria::class, "caso_id");
    }

    public function Archivo()
    {
        return $this->hasMany(Archivo::class, "caso_id");
    }
    // public function setfechaVencimientoAttribute($value)
    // {
    //     date_default_timezone_set("America/Guayaquil");
    //     $this->attributes["fecha_vencimiento"] = Carbon::now();
    // }
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
