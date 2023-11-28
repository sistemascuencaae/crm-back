<?php

namespace App\Models\mail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorreoElectronico extends Model
{
    protected $table = 'crm.correo_electronico';

    protected $fillable = [
        'asunto',
        'texto',
    ];

    // public function setCreatedAtAttribute($value)
    // {
    //     date_default_timezone_set("America/Guayaquil");
    //     $this->attributes["created_at"] = Carbon::now();
    // }
    // public function setUpdatedAtAttribute($value)
    // {
    //     date_default_timezone_set("America/Guayaquil");
    //     $this->attributes["updated_at"] = Carbon::now();
    // }

}
