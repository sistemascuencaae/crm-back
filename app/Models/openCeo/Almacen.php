<?php

namespace App\Models\openCeo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'public.almacen';
    use HasFactory;
    protected $primaryKey = 'alm_id';

    protected $fillable = [
        "alm_id",
        "alm_codigo",
        "alm_nombre",
        "ent_id",
        "dir_id",
        "cli_id",
        "alm_activo",
        "locked",
        "cen_id",
        "ubi_id",
        "alm_nombre_tmp",
        "alm_nombre_tmp2",
    ];
}