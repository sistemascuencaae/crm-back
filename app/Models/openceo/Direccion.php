<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Direccion extends Model
{
    protected $table = 'public.direccion';
    protected $primaryKey = 'dir_id';
    public $timestamps = false;
    protected $fillable = [
        "dir_calle_principal",
        "dir_numeracion",
        "dir_calle_secundaria",
        "dir_nombre_edificio",
        "dir_piso",
        "dir_oficina",
        "dir_indicaciones_adicionales",
        "dir_latitud",
        "dir_longitud",
        "locked",
        "dir_imagen",
    ];
}
