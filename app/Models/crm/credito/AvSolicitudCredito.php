<?php

namespace App\Models\crm\credito;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvSolicitudCredito extends Model
{
    // use AuditableTrait;

    use HasFactory;

    protected $table = 'crm.av_solicitud_credito';

    protected $primaryKey = 'ent_id';

    protected $fillable = [
        "ent_id",
        "ent_identificacion",
        "ent_nombres",
        "ent_apellidos",
        "pai_nombre",
        "ctn_nombre",
        "prq_nombre",
        "nivel_educacion",
        "cactividad_economica",
        "numero_dependientes",
        "nombre_empresa",
        "telefono_domicilio",
        "tipo_empresa",
        "celulares",
        "direccion",
        "numero_casa",
        "calle_secundaria",
        "referencias_direccion",
        "telefono_lugar_trabajo",
        "fecha_ingreso",
        "ingresos_totales",
        "gastos_totales",
        "activos_totales",
        "pasivos_totales",
    ];
    public function referencias()
    {
        return $this->hasMany(ReferenciasAnexoOpenceo::class, "ent_id","ent_id");
    }


}
