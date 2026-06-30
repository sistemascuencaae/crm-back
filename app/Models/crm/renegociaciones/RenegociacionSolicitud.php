<?php

namespace App\Models\crm\renegociaciones;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RenegociacionSolicitud extends Model
{
    protected $table = 'crm.renegociacion_solicitud';

    protected $fillable = [
        'identificacion',
        'nombre_cliente',
        'numero_factura',
        'fecha_factura',
        'codigo_producto',
        'descripcion_producto',
        'numero_cuota',
        'fecha_cambio',
        'motivo_cambio',
        'tipo_factura',
        'aprobado',
        'ejecutado',
        'observacion_cartera',
        'observacion_sistemas',
        'created_by',
        'updated_by',
        'codigo_lote',
    ];

    protected $casts = [
        'fecha_factura' => 'date',
        'fecha_cambio' => 'date',
        'aprobado' => 'boolean',
        'ejecutado' => 'boolean',
        'numero_cuota' => 'integer',
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
