<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DSemaforo extends Model
{
    use HasFactory;

    protected $table = 'crm.dsemaforo';

    protected $fillable = [
        'id',
        'csemaforo_id',
        'color',
        'etiqueta',
        'inicio',
        'fin',
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

    // Relación con cabecera de semáforo
    public function csemaforo()
    {
        return $this->belongsTo(CSemaforo::class, 'csemaforo_id', 'id');
    }
}
