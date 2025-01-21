<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormularioCampo extends Model
{
    use HasFactory;

    protected $table = 'crm.formulario_campo';
    protected $fillable = [
        "form_id",
        "nombre",
        "etiqueta",
        "tipo",
        "requerido",
        "descripcion",
        "orden",
        "estado",
        "opciones_campo",
        "form_control_name",
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

}
