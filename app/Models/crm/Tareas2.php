<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Tareas2 extends Model
{

    protected $table = 'crm.tareas2';

    protected $fillable = [
        "caso_id",
        "ctt_id",
        "dtt_id",
        "nombre",
        "requerido",
        "marcado",
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