<?php

namespace App\Models\activos;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activo extends Model
{
    use HasFactory;

    protected $table = 'crm.activo';

    protected $fillable = [
        "id_tipo_activo",
        "id_marca",
        "id_estado_activo",
        "codigo",
        "serie",
        "modelo",
        "equipo",
        "costo",
        "iva",
        "total",
        "estado",
        "observacion",
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

    public function tipo_activo()
    {
        return $this->belongsTo(TipoActivo::class, 'id_tipo_activo', 'id');
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca', 'id');
    }

    public function estado_activo()
    {
        return $this->belongsTo(EstadoActivo::class, 'id_estado_activo', 'id');
    }

    // Relación con Dacta (detalles de acta)
    public function detalles_acta()
    {
        return $this->hasMany(Dacta::class, 'id_activo', 'id');
    }

    // La última acta del activo (más reciente por número a través de cacta)
    public function ultima_acta()
    {
        return $this->hasOneThrough(
            Cacta::class,      // Modelo final
            Dacta::class,      // Modelo intermedio
            'id_activo',       // Foreign key en dacta
            'id',              // Foreign key en cacta
            'id',              // Local key en activo
            'id_cacta'         // Local key en dacta
        )
            ->orderBy('numero', 'desc')
            ->with(['localidad', 'departamento', 'user']);
    }
}
