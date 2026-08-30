<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CSemaforo extends Model
{
    use HasFactory;

    protected $table = 'crm.csemaforo';

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

    // Relación con detalle de semáforo (rangos)
    public function dsemaforos()
    {
        return $this->hasMany(DSemaforo::class, 'csemaforo_id', 'id');
    }

    // Relación con tipos de actividad
    public function tiposActividad()
    {
        return $this->hasMany(TipoActividad::class, 'csemaforo_id', 'id');
    }
}
