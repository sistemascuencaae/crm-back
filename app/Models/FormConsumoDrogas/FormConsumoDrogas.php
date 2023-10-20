<?php

namespace App\Models\FormConsumoDrogas;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormConsumoDrogas extends Model
{
    use HasFactory;
    protected $table = 'hclinico.form_consumo_drogas';

    protected $fillable = [
        "pac_id",
        "cargo",
        "anio_nacimiento",
        "estadocivil",
        "genero",
        "nivelinstruccion",
        "numerohijos",
        "etnia",
        "discapacidad",
        "problemaconsumo",
        "tratamiento",
        "capacitacion",
        "otra_auto_etnica",
        "otra_droga",
        "otro_factor",
        "porcentaje_discapacidad",
    ];
    public function consumoDroga()
    {
        return $this->hasMany(ConsumoDroga::class, "form_cd_id", "id");
    }
    public function consumoFactores()
    {
        return $this->hasMany(ConsumoFactores::class, "form_cons_dro_id", "id");
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
