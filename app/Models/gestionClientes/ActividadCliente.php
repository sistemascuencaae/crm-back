<?php

namespace App\Models\gestionClientes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadCliente extends Model
{
    use HasFactory;

    protected $table = 'crm.actividad_cliente';

    protected $fillable = [
        'id',
        'cliente_id',
        'user_id',
        'tipo_actividad_id',
        'prioridad_actividad_id',
        'estado_actividad_id',
        'fecha_inicio',
        'hora_inicio',
        'fecha_cierre',
        'hora_cierre',
        'observacion_inicio',
        'observacion_cierre',
        'valor',
        'es_reasignado',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_cierre' => 'datetime',
        'es_reasignado' => 'boolean',
        'valor' => 'decimal:2'
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

    // Relación con cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
    }

    // Relación con usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Relación con tipo de actividad
    public function tipoActividad()
    {
        return $this->belongsTo(TipoActividad::class, 'tipo_actividad_id', 'id');
    }

    // Relación con prioridad
    public function prioridadActividad()
    {
        return $this->belongsTo(PrioridadActividad::class, 'prioridad_actividad_id', 'id');
    }

    // Relación con estado
    public function estadoActividad()
    {
        return $this->belongsTo(EstadoActividad::class, 'estado_actividad_id', 'id');
    }

    // Relación con etiquetas
    public function etiquetas()
    {
        return $this->hasMany(EtiquetaActividad::class, 'actividad_cliente_id', 'id');
    }

    // Relación con adjuntos
    public function adjuntos()
    {
        return $this->hasMany(AdjuntoCliente::class, 'actividad_cliente_id', 'id');
    }
}
