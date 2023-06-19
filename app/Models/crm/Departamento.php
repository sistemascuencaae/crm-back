<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Departamento extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'crm.departamento';
    protected $primaryKey = 'dep_id';
    use SoftDeletes;
    protected $fillable = [
        'dep_nombre',
        'dep_descripcion'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Flujo()
    {
        return $this->hasMany(Flujo::class, "dep_id");
    }
}