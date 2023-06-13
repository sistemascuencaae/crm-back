<?php

namespace App\Models\crm;

use App\Models\crm\Tarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Flujo extends Model
{
    protected $table = 'crm.flujo';
    protected $primaryKey = 'id';
     use SoftDeletes;
    protected $fillable = [
        'dep_id',
        'nombre',
        'descripcion',
        'genearar_tarea',
        'orden',
        'estado'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function Departamento()
    {
       return $this->belongsTo(Departamento::class,"dep_id");
    }

    public function Tarea()
    {
        return $this->hasMany(Tarea::class,"flujo_id");
    }



}
