<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudesCreditosWeb extends Model
{
    use HasFactory;

    protected $table = 'crm.solicitudes_creditos_web';

    protected $fillable = [
        "identificacion",
        "tipoidentificacion",
        "nombres",
        "apellidos",
        "direccion",
        "prv_nombre",
        "ctn_nombre",
        "prq_nombre",
        "email",
        "telefono",
        "ip",
        "mac",
        "aceptar_terminos_condiciones",
        "confirmacion_terminos_condiciones",
        "latitud",
        "longitud",
        "formulario_tipo",
        "archivo",
        "empresa",
        "informacion_ip",
        "producto_acomprar",
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