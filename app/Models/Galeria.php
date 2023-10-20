<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Galeria extends Model
{
    protected $table = 'hclinico.galeria';
    protected $primaryKey = 'ga_id';

    protected $fillable = [
        'formulario_id',
        'ga_seccion',
        'ga_titulo',
        'ga_descripcion',
        'ga_imagen',
        'ga_estado',
        'ga_tipo_formulario',
    ];


    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}

