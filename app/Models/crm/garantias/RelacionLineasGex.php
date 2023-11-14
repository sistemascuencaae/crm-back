<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class RelacionLineasGex extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.rel_linea_gex';

    protected $primaryKey = 'tpr_id, pro_id';

    public $timestamps = false;

    protected $fillable = [
        "tpr_id".
        "pro_id",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}