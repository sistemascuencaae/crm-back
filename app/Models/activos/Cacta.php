<?php

namespace App\Models\activos;

use App\Models\crm\Departamento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cacta extends Model
{
    use HasFactory;

    protected $table = 'crm.cacta';

    protected $fillable = [
        "id_user",
        "id_localidad",
        "id_departamento",
        "numero",
        "recepcion_fisica_acta",
        "recibido_por",
        // "impresion",
        "estado",
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

    // Relación con User
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    // Relación con Localidad
    public function localidad()
    {
        return $this->belongsTo(Localidad::class, 'id_localidad', 'id');
    }

    // Relación con Departamento
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_departamento', 'id');
    }

    // Relación con Dacta (detalles)
    public function detalles()
    {
        return $this->hasMany(Dacta::class, 'id_cacta', 'id')->orderBy('secuencia', 'asc');
    }
}
