<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoAdjunto extends Model
{
    use HasFactory;

    protected $table = 'crm.tipo_adjunto';

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

    // Relación con adjuntos
    public function adjuntos()
    {
        return $this->hasMany(AdjuntoCliente::class, 'tipo_adjunto_id', 'id');
    }
}
