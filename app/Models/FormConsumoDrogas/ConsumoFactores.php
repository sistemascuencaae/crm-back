<?php

namespace App\Models\FormConsumoDrogas;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumoFactores extends Model
{
    use HasFactory;
    protected $table = 'hclinico.consumo_factores';

    protected $fillable = [
        "form_cons_dro_id",
        "faccons_id",
    ];
    public function factoresPsConsumo()
    {
        return $this->belongsTo(FactoresPsConsumo::class, "faccons_id", "id");
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
