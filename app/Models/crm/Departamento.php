<?php

namespace App\Models\crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Departamento extends Model
{
    protected $table = 'crm.departamento';
    protected $primaryKey = 'dep_id';

    protected $fillable = [
        'dep_nombre',
        'dep_descripcion',
        'estado'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users()
    {
        return $this->hasMany(User::class, "dep_id");
    }
}
