<?php

namespace App\Models\crm;

use App\Models\crm\Estados;
use App\Models\crm\Fase;
use App\Models\crm\RespuestasCaso;
use App\Models\crm\Tablero;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadosFormulas extends Model
{
    use HasFactory;

    protected $table = 'crm.estados_formulas';

    protected $fillable = ["est_id_actual", "resp_id", "est_id_proximo", "tablero_id", "fase_id", 'tab_id'];

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
        return $this->belongsTo(Estados::class, "est_id_actual");
    }

    public function respuesta_caso()
    {
        return $this->belongsTo(RespuestasCaso::class, "resp_id");
    }

    public function estado_proximo()
    {
        return $this->belongsTo(Estados::class, "est_id_proximo");
    }

    public function tablero()
    {
        return $this->belongsTo(Tablero::class, "tablero_id");
    }

    public function fase()
    {
        return $this->belongsTo(Fase::class, "fase_id");
    }
}
