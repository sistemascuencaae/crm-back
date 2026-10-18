<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EtiquetaActividad extends Model
{
    use HasFactory;

    protected $table = 'crm.etiquetas_actividad';

    protected $fillable = [
        'id',
        'actividad_cliente_id',
        'nombre',
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

    // Relación con actividad
    public function actividad()
    {
        return $this->belongsTo(ActividadCliente::class, 'actividad_cliente_id', 'id');
    }
}
