<?php

namespace App\Models\activos;

use App\Models\crm\Departamento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acta extends Model
{
    use HasFactory;

    protected $table = 'crm.acta';

    protected $fillable = [
        "id_activo",
        "id_user",
        "id_localidad",
        "id_departamento",
        "numero",
        "secuencia",
        "recepcion_fisica_acta",
        "impresion",
        "recibido_por",
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

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'id_activo', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    public function localidad()
    {
        return $this->belongsTo(Localidad::class, 'id_localidad', 'id');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_departamento', 'id');
    }
}
