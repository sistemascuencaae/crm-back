<?php

namespace App\Models\crm;

use App\Models\crm\Estados;
use App\Models\crm\Fase;
use App\Models\crm\Tablero;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCasoFormulas extends Model
{
    use HasFactory;

    protected $table = 'crm.tipo_caso_formulas';

    protected $fillable = [
        "dep_id",
        "tab_id",
        "tc_id",
        "user_id",
        "prioridad",
        "estado_2",
        "tiempo_vencimiento",
        "fase_id",
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

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, "dep_id");
    }

    public function tablero()
    {
        return $this->belongsTo(Tablero::class, "tab_id");
    }

    public function tipoCaso()
    {
        return $this->belongsTo(TipoCaso::class, "tc_id");
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function estadodos()
    {
        return $this->belongsTo(Estados::class, "estado_2");
    }

    public function fase()
    {
        return $this->belongsTo(Fase::class, "fase_id");
    }

}