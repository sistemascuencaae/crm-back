<?php

namespace App\Models\comercializacion;

use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentasxAgencia extends Model
{

    use HasFactory;
    protected $table = 'public.av_reporte_ventasxagencia';

    protected $fillable = [
        'tipo_nota',
        'periodo',
        'fecha',
        'comprobante',
        'total',
        'subtotal_descuentos',
        'subtotal_descuentos_interes',
        'id_agente_factura',
        'emp_abreviacion',
        'agente_factura',
        'interes',
        'politica',
        'politica2',
        'ent_identificacion',
        'cliente',
        'cti_sigla',
        'alm_codigo',
        'almacen',
        'pve_numero',
        'factura_afectada'
    ];

}
