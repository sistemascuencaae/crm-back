<?php

namespace App\Models\gestiones;

use App\Models\crm\Caso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GestionCaso extends Model
{
    protected $table = 'crm.gestion_caso';

    protected $fillable = [
        'gestion_id',
        'caso_id',
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

    public function caso(){
        return $this->belongsTo(Caso::class, 'caso_id');
    }

}
