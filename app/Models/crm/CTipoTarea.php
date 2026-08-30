<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
class CTipoTarea extends Model
{
    use HasFactory;

    protected $table = 'crm.ctipo_tarea';

    protected $fillable = ["nombre", "estado",];
//  "tab_id"

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

    public function dTipoTarea()
    {
        return $this->hasMany(DTipoTarea::class, "ctt_id");
    }
}