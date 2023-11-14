<?php

namespace App\Models\crm;

use App\Models\crm\CTipoResultadoCierre;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadesFormulas extends Model
{
    use HasFactory;

    protected $table = 'crm.actividades_formulas';

    protected $fillable = ["result_id_actual", "result_id", "result_id_proximo", 'tab_id'];

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

    public function estado_actual()
    {
        return $this->belongsTo(CTipoResultadoCierre::class, "result_id_actual");
    }

    public function respuesta_actividad()
    {
        return $this->belongsTo(CTipoResultadoCierre::class, "result_id");
    }

    public function estado_proximo()
    {
        return $this->belongsTo(CTipoResultadoCierre::class, "result_id_proximo");
    }
}