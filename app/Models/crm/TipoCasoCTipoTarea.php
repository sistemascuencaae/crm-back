<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TipoCasoCTipoTarea extends Model
{
    use HasFactory;

    protected $table = 'crm.tipo_caso_ctipo_tarea';

    protected $fillable = ["tipo_caso_id", "ctipo_tarea_id"];

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

    // public function tipoCaso()
    // {
    //     return $this->belongsTo(TipoCaso::class, "tipo_caso_id", "id");
    // }

    public function cTipoTarea()
    {
        return $this->belongsTo(CTipoTarea::class, "ctipo_tarea_id","id");
    }
}