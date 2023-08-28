<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VistaMisCasos extends Model
{

    protected $table = 'crm.av_mis_casos';

    protected $fillable = [
        "id_usuario_miembro",
        "usuario_miembro",
        "dueno_caso",
        "nombre",
        "caso_id",
        "fecha_vencimiento",
        "created_at",
        "ent_id",
        "cliente",
        "fase_nombre",
        "fase_color",
        "prioridad",
        "tab_id",
        "nombre_tablero",
        "estado_2",
        "tc_id",
    ];

    public function miembros(){
        return $this->hasMany(Miembros::class, "caso_id", "caso_id");
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
