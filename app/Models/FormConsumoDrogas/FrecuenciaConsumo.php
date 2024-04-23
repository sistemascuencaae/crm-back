<?php

namespace App\Models\FormConsumoDrogas;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrecuenciaConsumo extends Model
{
    use HasFactory;
     protected $table = 'hclinico.frecuencia_consumo';

    protected $fillable = [
        "nombre",
        "estado",
    ];
    public function consumoDroga()
    {
        return $this->hasMany(ConsumoDroga::class, "fcd_id", "id");
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
