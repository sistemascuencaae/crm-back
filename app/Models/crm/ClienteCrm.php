<?php

namespace App\Models\crm;

use App\Models\crm\ReferenciasCliente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteCrm extends Model
{
    use HasFactory;

    protected $table = 'crm.cliente';

    protected $fillable = [
        "ent_id",
        "ent_identificacion",
        "ent_nombres",
        "ent_apellidos",
        "pai_nombre",
        "ctn_nombre",
        "prq_nombre",
        "prv_nombre",
        "nivel_educacion",
        "cactividad_economica",
        "numero_dependientes",
        "nombre_empresa",
        "telefono_domicilio",
        "celulares",
        "tipo_empresa",
        "telefono_trabajo",
        "direccion",
        "numero_casa",
        "calle_secundaria",
        "referencias_direccion",
        "telefono_lugar_trabajo1",
        "telefono_lugar_trabajo2",
        "trabajo_direccion",
        "fecha_ingreso",
        "ingresos_totales",
        "gastos_totales",
        "activos_totales",
        "pasivos_totales",
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

}