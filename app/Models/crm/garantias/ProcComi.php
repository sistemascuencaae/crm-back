<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class ProcComi extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.cproc_comisiones';

    protected $primaryKey = 'procomi_id, cumpli_id, alm_id';

    public $timestamps = false;

    protected $fillable = [
        "procomi_id",
        "cumpli_id",
        "alm_id",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}

class ProcComiDet extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.dproc_comisiones';

    protected $primaryKey = 'procomi_id, cumpli_id, alm_id, emp_id';

    public $timestamps = false;

    protected $fillable = [
        "procomi_id",
        "cumpli_id",
        "alm_id",
        "emp_id",
        "venta_gex",
        "cumplimiento",
        "cumplimiento_gex",
        "porc_vendedor",
        "porc_jfa",
        "porc_jfz",
        "porc_jfv",
        "porc_jfg",
        "valor_vendedor",
        "valor_jfa",
        "valor_jfz",
        "valor_jfv",
        "valor_jfg",
    ];
}