<?php

namespace App\Models\crm\renegociaciones;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Doctran extends Model
{
    protected $table = 'crm.historial_doctran';

    protected $fillable = [
        'ddo_id',
        'ddo_transacc',
        'ccm_id',
        'ddo_num_pago',
        'ddo_debcre',
        'ddo_monto',
        'ddo_fechaven',
        'cli_id',
        'ddo_cancelado',
        'ddo_monto_cancelado',
        'ddo_agente',
        'locked',
        'ddo_fecha_emision',
        'ddo_doctran',
        'dco_id',
        'ddo_numfac',
        'ddo_emisor',
        'ddo_nrocuenta',
        'ddo_observacion',
        'codigo_historial'
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["updated_at"] = Carbon::now();
    }

}
