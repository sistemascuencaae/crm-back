<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequerimientoCaso extends Model
{
    use HasFactory;

    use SoftDeletes;
    protected $table = 'crm.requerimientos_caso';


    protected $fillable = [
        "descripcion",
        "caso_id",
        "marcado",
        "estado",
        "tipo_req_id",
        "user_requiere_id",
        "titulo",
        "tipo_campo",
        "requerido",
        "valor_date",
        "valor_int",
        "valor_boolean",
        "valor_varchar",
        "valor_decimal",
        "html_render",
        "valor",
        "form_control_name",
        "valor_multiple",
        "orden",
        "valor_lista",
        'esimagen',
        'galerias_id',
        'archivos_id',
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
}