<?php

namespace App\Models\crm\credito;

use App\Models\crm\Entidad;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class solicitudCredito extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.solicitud_credito';
    protected $fillable = [
        "fecha_actual",
        "vendedor",
        "agencia",
        "codigo_cliente",
        // Datos Deudor
        "nacionalidad",
        "ruc_cedula",
        "nombre_razon_social",
        "nivel_educacion",
        "cargas_familiares",
        "telefono_domicilio",
        "numero_celular",
        "calle_principal",
        "calle_secundaria",
        "referencia_direccion",

        "provincia",
        "canton",
        "parroquia",
        "actividad_economica",
        "nombre_empresa",
        "tipo_empresa",
        "direccion",
        "telefono_trabajo1",
        "telefono_trabajo2",
        "fecha_ingreso",
        // Informacion economica
        "total_ingresos",
        "total_egresos",
        "total_ingresos_egresos",
        //Referencias
        "referencias"
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
    public function setDeletedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["deleted_at"] = Carbon::now();
    }

    public function Entidad()
    {
        return $this->belongsTo(Entidad::class, "ent_id");
    }
}