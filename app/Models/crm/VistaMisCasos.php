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
        "creador_caso",
        "nombre",
        "caso_id",
        "fecha_vencimiento",
        "fecha_inicio",
        "ent_id",
        "cliente",
        "fase_nombre",
        "fase_color",
        "prioridad",
        "tab_id",
        "nombre_tablero",
        "estado_2",
        "tc_id",
        "user_creador_id",
        "datos_formulario",
        "tipo_caso",
        "nombre_categoria_caso",
        "acc_publico",
    ];

    public function miembros(){
        return $this->hasMany(Miembros::class, "caso_id", "caso_id");
    }
    public function estadodos()
    {
        return $this->belongsTo(Estados::class, "estado_2", "id");
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
