<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class Metas extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.cmeta';

    protected $primaryKey = 'meta_id, alm_id';

    public $timestamps = false;

    protected $fillable = [
        "meta_id",
        "alm_id",
        "monto_meta",
        "porc_meta_gex",
        "monto_meta_gex",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}

class MetasDet extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.dmeta';

    protected $primaryKey = 'meta_id, alm_id, emp_id';

    public $timestamps = false;

    protected $fillable = [
        "meta_id",
        "alm_id",
        "emp_id",
        "porc_meta",
        "monto_meta",
        "porc_meta_gex",
        "monto_meta_gex",
    ];
}