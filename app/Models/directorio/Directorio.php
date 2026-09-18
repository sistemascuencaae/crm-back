<?php

namespace App\Models\directorio;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Directorio extends Model
{
    use HasFactory;

    protected $table = 'crm.directorio';

    protected $fillable = [
        "zona",
        "jefe_zonal",
        "almacen",
        "jefe_almacen",
        "direccion",
        "horario",
        "contacto1",
        "descripcion_contacto1",
        "contacto2",
        "descripcion_contacto2",
        "contacto3",
        "descripcion_contacto3",
        "contacto4",
        "descripcion_contacto4",
        "correo1",
        "descripcion_correo1",
        "correo2",
        "descripcion_correo2",
        "correo3",
        "descripcion_correo3",
        "correo4",
        "descripcion_correo4",
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
