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

    // 27-nov: la dirección principal de un banco (CONTABILIDAD > Bancos): la columna y la clave que espera
    // ventas.fn_banco_guardar en p_json->'direccion'.
    public const CLAVES_FUNCION_BANCO = [
        'dir_calle_principal' => 'calle_principal',
        'dir_calle_secundaria' => 'calle_secundaria',
        'dir_numeracion' => 'numeracion',
        'dir_nombre_edificio' => 'edificio',
        'dir_piso' => 'piso',
        'dir_oficina' => 'oficina',
        'dir_latitud' => 'latitud',
        'dir_longitud' => 'longitud',
        'dir_indicaciones_adicionales' => 'indicaciones',
    ];
}
