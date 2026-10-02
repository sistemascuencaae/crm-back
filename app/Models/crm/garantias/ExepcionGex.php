<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class ExepcionGex extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.excepciones_gex';

    protected $primaryKey = 'exce_id';

    public $timestamps = false;

    protected $fillable = [
        "exce_id",
        "pro_id",
        "config_id",
        "porc_gex",
        "fecha_ini",
        "fecha_fin",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}