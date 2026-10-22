<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPruebaFlutter extends Model
{
    protected $table = 'crm.pruebas_flutter';
    protected $fillable = ['uuid', 'nombre', 'latitud', 'longitud', 'fecha_captura', 'secuencia'];
}