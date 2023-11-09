<?php

namespace App\Models\FormConsumoDrogas;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumoDroga extends Model
{
    use HasFactory;

     protected $table = 'hclinico.consumo_droga';

    protected $fillable = [
        "pcd_id",
        "fcd_id",
        "form_cd_id",
        "droga_principal"
    ];
    public function frecuencia()
    {
        return $this->belongsTo(FrecuenciaConsumo::class, "fcd_id", "id");
    }
    public function pramconsudroga()
    {
        return $this->belongsTo(ParametroConsumoDrogas::class, "pcd_id", "id");
    }
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
}
