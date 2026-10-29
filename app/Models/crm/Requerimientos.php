<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requerimientos extends Model
{
    use HasFactory;

    protected $table = 'crm.requerimientos_predefinidos';

    protected $fillable = [
        "nombre",
        "estado",
        "tipo",
        "fase_id",
        "tab_id",
        "user_id",
        "valor_lista",
        "requerido",
        "orden",
        "acc_publico",
        "desc_requerida",
        "tipo_caso_id",
        "minimo",
        "maximo",
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

    public function tipo_caso()
    {
        return $this->belongsTo(TipoCaso::class, "tipo_caso_id");
    }

    // 28-oct: las fases donde el requerimiento se habilita; se escriben con crm.fn_requerimientos_predefinidos_*.
    public function getFasesHabilitadasAttribute($value)
    {
        return self::fasesDesdePostgres($value);
    }

    // integer[] de PostgreSQL ('{536,540}' o '{}') -> [536, 540] / []; null se queda null.
    public static function fasesDesdePostgres($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return array_map('intval', $value);
        }
        $texto = trim((string) $value, "{} \t\n\r");
        return $texto === '' ? [] : array_map('intval', explode(',', $texto));
    }

}
