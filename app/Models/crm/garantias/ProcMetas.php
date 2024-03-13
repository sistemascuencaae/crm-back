<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class ProcMetas extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.cproc_metas';

    protected $primaryKey = 'cumpli_id, alm_id';

    public $timestamps = false;

    protected $fillable = [
        "cumpli_id",
        "alm_id",
        "mes",
        "anio",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}

class ProcMetasDet extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.dproc_metas';

    protected $primaryKey = 'cumpli_id, alm_id, emp_id';

    public $timestamps = false;

    protected $fillable = [
        "cumpli_id",
        "alm_id",
        "emp_id",
        "venta",
        "venta_gex",
        "porc_meta",
        "monto_meta",
        "porc_meta_gex",
        "monto_meta_gex",
        "cumplimiento",
        "cumplimiento_gex",
        "tipo_emp",
    ];
}