<?php

namespace App\Models\gestiones;

use App\Models\configuracion\Agencia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Gestion extends Model
{
    protected $table = 'crm.gestion';

    protected $fillable = [
        'agencia_id',
        'user_id',
        'total_interes_calculado',
        'total_acuerdo_pago',
        'total_cobrado',
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

    public function gestion_caso(){
        return $this->hasMany(GestionCaso::class, 'gestion_id');
    }

    public function agencia(){
        return $this->belongsTo(Agencia::class, 'agencia_id', 'codigo');
    }
}
