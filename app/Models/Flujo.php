<?php

namespace App\Models;

use App\Models\Tarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Flujo extends Model
{
    protected $table = 'public.flujo';
    protected $primaryKey = 'id';
     use SoftDeletes;
    protected $fillable = [
        'id_dep',
        'fase',
        'descripcion',
        'genearar_tarea',
        'posicion'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Tarea()
    {
        return $this->hasMany(Tarea::class,"flujo_id");
    }



}
