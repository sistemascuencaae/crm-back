<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'public.almacen';

    protected $fillable = [
        'alm_id',
        'alm_codigo',
        'alm_nombre',
        'ent_id',
        'dir_id',
        'cli_id',
        'alm_activo',
        'locked',
        'cen_id',
        'ubi_id',
        'alm_nombre_tmp',
        'alm_nombre_tmp2',
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
