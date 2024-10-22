<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Model;

class AutoTrataDatos extends Model
{
    protected $table = 'crm.auto_trata_datos';

    protected $fillable = [
        'identificacion',
        'nombres',
        'apellidos',
        'nombre_completo',
        'fecha_registro',
        'email',
        'telefono_principal',
        'tipo_documento'
    ];
   
}
