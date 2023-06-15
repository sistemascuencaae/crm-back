<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarea extends Model
{
    protected $table = 'crm.tarea';
    protected $primaryKey = 'id';
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'flujo_id',
        'orden',
        'estado',
        'comentario',
        'info1',
        'info2',
        'info3',
        'info4',
        'info5',
        'info6',
        'info7',
        'ent_id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function Flujo()
    {
       return $this->belongsTo(Flujo::class,"flujo_id");
    }

    public function Etiqueta()
    {
        return $this->hasMany(Etiqueta::class,"tar_id");
    }

    public function Galeria()
    {
        return $this->hasMany(Galeria::class,"tar_id");
    }

    public function Archivo()
    {
        return $this->hasMany(Archivo::class,"tar_id");
    }

}
