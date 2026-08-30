<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrioridadActividad extends Model
{
    use HasFactory;

    protected $table = 'crm.prioridad_actividad';

    protected $fillable = [
        'id',
        'nombre',
        'estado',
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

    // Relación con actividades
    public function actividades()
    {
        return $this->hasMany(ActividadCliente::class, 'prioridad_actividad_id', 'id');
    }
}
