<?php

namespace App\Models\activos;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoActivo extends Model
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
}
