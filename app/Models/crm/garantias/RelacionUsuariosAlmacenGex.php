<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class RelacionUsuariosAlmacenGex extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.rel_usuario_almacenes';

    protected $primaryKey = 'usu_id, alm_id';

    public $timestamps = false;

    protected $fillable = [
        "usu_id".
        "alm_id",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}