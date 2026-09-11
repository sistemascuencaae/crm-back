<?php

namespace App\Models\crm\renegociaciones;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DdocumentoHistorial extends Model
{
    protected $table = 'crm.ddocumento_historial';

    protected $fillable = [
        'codigo_lote',
        'user_id',
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
        'factura_cambio',
        'cuota_cambio',
        'fecha_cambio',

        // Snapshot DESPUÉS (sufijo _2)
        'ddo_id_2',
        'ddo_transacc_2',
        'ccm_id_2',
        'ddo_num_pago_2',
        'ddo_debcre_2',
        'ddo_monto_2',
        'ddo_fechaven_2',
        'cli_id_2',
        'ddo_cancelado_2',
        'ddo_monto_cancelado_2',
        'ddo_agente_2',
        'locked_2',
        'ddo_fecha_emision_2',
        'ddo_doctran_2',
        'dco_id_2',
        'ddo_numfac_2',
        'ddo_emisor_2',
        'ddo_nrocuenta_2',
        'ddo_observacion_2',

        // Trazabilidad del workflow de solicitud
        'id_solicitud',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ddo_cancelado' => 'boolean',
        'locked' => 'boolean',
        'ddo_monto' => 'decimal:4',
        'ddo_monto_cancelado' => 'decimal:4',
        'ddo_fechaven' => 'datetime',
        'ddo_fecha_emision' => 'date',
        'fecha_cambio' => 'datetime',

        'ddo_cancelado_2' => 'boolean',
        'locked_2' => 'boolean',
        'ddo_monto_2' => 'decimal:4',
        'ddo_monto_cancelado_2' => 'decimal:4',
        'ddo_fechaven_2' => 'datetime',
        'ddo_fecha_emision_2' => 'date',
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['created_at'] = Carbon::now();
    }

    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['updated_at'] = Carbon::now();
    }
}
