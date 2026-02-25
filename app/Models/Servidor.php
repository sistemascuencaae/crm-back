<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servidor extends Model
{
    protected $table = 'crm.servidores';

    protected $fillable = [
        'nombre',
        'ip',
        'usuario',
        'clave',
        'archivo',
        'estado',
        'link',
    ];

    protected $hidden = [
        'usuario',
        'clave',
        'archivo',
    ];
}
