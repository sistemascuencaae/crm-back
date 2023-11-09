<?php

namespace App\Models\crm;

use App\Models\crm\ReferenciasCliente;
use App\Models\crm\TelefonosCliente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteCrm extends Model
{
    use HasFactory;

    protected $table = 'crm.cliente';

    protected $fillable = [
        "id",
        "identificacion",
        "nombres",
        "apellidos",
        "pai_nombre",
        "ctn_nombre",
        "prq_nombre",
        "prv_nombre",
        "nivel_educacion",
        "cactividad_economica",
        "numero_dependientes",
        "nombre_empresa",
        "tipo_empresa",
        "direccion",
        "numero_casa",
        "calle_pricipal",
        "calle_secundaria",
        "referencias_direccion",
        "trabajo_direccion",
        "fecha_ingreso",
        "ingresos_totales",
        "gastos_totales",
        "activos_totales",
        "pasivos_totales",

        "cedula_conyuge",
        "nombres_conyuge",
        "apellidos_conyuge",
        "direccion_conyuge",
        "email_conyuge",
        "sexo_conyuge",
        "fecha_nacimiento_conyuge",

        "telefono_conyuge_1",
        "telefono_conyuge_2",
        "telefono_conyuge_3",
        "observacion_conyuge",
        "fechanacimiento",
        "tipo_identificacion",
        "email",
        "nombre_comercial"

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

    public function referencias()
    {
        return $this->hasMany(ReferenciasCliente::class, "cli_id", "id");
    }

    public function telefonos()
    {
        return $this->hasMany(TelefonosCliente::class, "cli_id", "id");
    }

}
