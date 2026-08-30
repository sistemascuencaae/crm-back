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
        'desc_requerida',
        'tab_id',
        'acc_publico',
        'cfa_id',
        'cfa_id_2',
        'cfa_id_3',
        'cfa_id_4',
        'cfa_id_5',
        'cfa_id_6',
        'cfa_id_7',
        'cfa_id_8',
        'cfa_id_9',
        'cfa_id_10',
        'pro_id',
        'pro_id_2',
        'pro_id_3',
        'pro_id_4',
        'pro_id_5',
        'pro_id_6',
        'pro_id_7',
        'pro_id_8',
        'pro_id_9',
        'pro_id_10',
        "minimo",
        "maximo",
        "valor_2",
        "valor_varchar_2",
        "editable",
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
