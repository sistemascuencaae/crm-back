<?php

namespace App\Models\crm;

use App\Models\crm\CTipoActividad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class DTipoActividad extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.dtipo_actividad';

    protected $fillable = ["descripcion", "fecha_inicio", "fecha_fin", "fecha_termino", "estado", "cta_id", "caso_id"];

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

}