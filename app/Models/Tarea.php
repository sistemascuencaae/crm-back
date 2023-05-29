<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarea extends Model
{
    protected $table = 'public.tarea';
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
        'info7'
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

}
