<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class MetasRecalc extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.cmeta_recal';

    protected $primaryKey = 'metare_id, alm_id';

    public $timestamps = false;

    protected $fillable = [
        "metare_id",
        "alm_id",
        "mes",
        "anio",
        "monto_meta",
        "porc_meta_gex",
        "monto_meta_gex",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}

class MetasDetRecalc extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.dmeta_recal';

    protected $primaryKey = 'metare_id, alm_id, emp_id';

    public $timestamps = false;

    protected $fillable = [
        "metare_id",
        "alm_id",
        "emp_id",
        "dias_perm_vac",
        "porc_meta",
        "monto_meta",
        "porc_meta_gex",
        "monto_meta_gex",
    ];
}