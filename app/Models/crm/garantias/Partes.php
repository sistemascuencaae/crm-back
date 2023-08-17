<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Partes extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.partes';

    protected $primaryKey = 'parte_id';

    public $timestamps = false;

    protected $fillable = [
        "parte_id",
        "descripcion",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}