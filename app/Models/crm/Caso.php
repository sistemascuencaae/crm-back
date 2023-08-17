<?php

namespace App\Models\crm;

use App\Models\crm\CTipoTarea;
use App\Models\User;
use App\Models\crm\Entidad;
use App\Models\crm\AVResumenCaso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Caso extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'crm.caso';

    protected $fillable = [
        "fas_id",
        "nombre",
        "descripcion",
        "estado",
        "orden",
        "created_at",
        "updated_at",
        "deleted_at",
        "ent_id",
        "user_id",
        "fecha_vencimiento",
        "prioridad",
        "bloqueado",
        "bloqueado_user",
        "ctt_id",
        "fase_anterior_id",
        "tc_id",
        "user_anterior_id",
        "user_creador_id",
        "estado_2",
        "fase_creacion_id",
        "tablero_creacion_id",
        "dep_creacion_id"
    ];

    public function userCreador()
    {
        return $this->belongsTo(User::class, "user_creador_id", "id");
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }

    public function tipocaso()
    {
        return $this->belongsTo(TipoCaso::class, "user_id", "id");
    }
    public function entidad()
    {
        return $this->belongsTo(Entidad::class, "ent_id");
    }
    public function resumen()
    {
        return $this->belongsTo(AVResumenCaso::class, "id");
    }
    public function tareas()
    {
        return $this->hasMany(Tareas::class, "caso_id");
    }
    public function miembros()
    {
        return $this->hasMany(Miembros::class, "caso_id");
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
    public function requerimientosCaso()
    {
        return $this->hasMany(Requerimientos::class, "caso_id");
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

    public function dTipoActividades()
    {
        return $this->hasMany(DTipoActividad::class, "caso_id");
    }

    public function cTipoTarea()
    {
        return $this->belongsTo(CTipoTarea::class, "ctt_id", 'id');
    }

    public function user_anterior()
    {
        return $this->belongsTo(User::class, "user_anterior_id", "id");
    }
}