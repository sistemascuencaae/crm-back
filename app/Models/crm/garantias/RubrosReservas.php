<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class RubrosReservas extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.rubro_reserva';

    protected $primaryKey = 'rr_id';

    public $timestamps = false;

    protected $fillable = [
        "rr_id",
        "descripcion",
        "porc_calculo",
        "capital_sn",
        "estado",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
}