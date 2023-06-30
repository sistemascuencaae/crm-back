<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Comentarios extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'crm.comentarios';
    protected $primaryKey = 'id';
    use SoftDeletes;
    protected $fillable = [
        'id',
        'user_id',
        'comentario',
        "caso_id",
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
