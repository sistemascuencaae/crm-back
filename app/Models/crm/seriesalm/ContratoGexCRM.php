<?php

namespace App\Models\crm\seriesalm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoGexCRM extends Model
{
    use HasFactory;

    protected $table = 'crm.contrato_gex';
    protected $fillable = [
        'alm_id',
        'celular',
        'cfa_id',
        'cfa_id_gex',
        'ciudad',
        'direccion',
        'email',
        'factura',
        'factura_gex',
        'fecha',
        'fecha_compra',
        'fecha_crea',
        'fecha_desde',
        'fecha_hasta',
        'fecha_modifica',
        'garantia_marca',
        'identificacion',
        'km_factor',
        'km_garantia',
        'marca',
        'meses_gex',
        'nom_almacen',
        'nom_cliente',
        'num_despacho',
        'numero',
        'pro_id',
        'producto',
        'provincia',
        'serie',
        'telefono',
        'tipo_identificacion',
        'tipo_servicio',
        'ubicacion',
        'usuario_crea',
        'usuario_modifica',

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
