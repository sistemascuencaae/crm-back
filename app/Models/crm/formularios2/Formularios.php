<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formularios extends Model
{
    use HasFactory;

    protected $table = 'crm.formularios';
    protected $fillable = [
        "nombre",
        "descripcion",
        "header",
        "footer",
        "color",
        "image",
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

    public function formulario_campo()
    {
        return $this->hasMany(FormularioCampo::class, "form_id", "id");
    }
}
