<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Comisiones extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.comisiones';

    protected $primaryKey = 'comi_id';

    public $timestamps = false;

    protected $fillable = [
        "comi_id",
        "comi_prod_ini",
        "comi_prod_fin",
        "comi_gex_ini",
        "comi_gex_fin",
        "porc_vendedor",
        "porc_jfa",
        "porc_jfz",
        "porc_jfv",
        "porc_jfg",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}