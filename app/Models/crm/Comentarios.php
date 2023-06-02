<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comentarios extends Model
{
    protected $table = 'public.comentarios';
    protected $primaryKey = 'id';
     use SoftDeletes;
    protected $fillable = [
        'id',
        'user_id',
        'comentario',
        'div_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'nombre_usuario',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];



}
