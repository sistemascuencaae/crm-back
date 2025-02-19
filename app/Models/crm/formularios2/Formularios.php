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
        "image_company",
        "estado",
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
    
    public function secciones()
    {
        return $this->hasMany(Secciones::class, "form_id", "id");
    }

    public function formularioUsuarios()
    {
        return $this->hasMany(FormulariosUsuarios::class, "form_id", "id");
    }
}
