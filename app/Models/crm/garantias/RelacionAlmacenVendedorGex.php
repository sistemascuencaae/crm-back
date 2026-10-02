<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class RelacionAlmacenVendedorGex extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.rel_almacen_vendedor';

    protected $primaryKey = 'alm_id, emp_id';

    public $timestamps = false;

    protected $fillable = [
        "alm_id",
        "emp_id",
        "tipo_empleado",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}