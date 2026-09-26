<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdjuntoCliente extends Model
{
    use HasFactory;

    protected $table = 'crm.adjuntos_cliente';

    protected $fillable = [
        'id',
        'tipo_adjunto_id',
        'cliente_id',
        'actividad_cliente_id',
        'nombre',
        'descripcion',
        'url',
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

    // Relación con tipo de adjunto
    public function tipoAdjunto()
    {
        return $this->belongsTo(TipoAdjunto::class, 'tipo_adjunto_id', 'id');
    }

    // Relación con cliente (puede ser null)
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
    }

    // Relación con actividad (puede ser null)
    public function actividad()
    {
        return $this->belongsTo(ActividadCliente::class, 'actividad_cliente_id', 'id');
    }
}
