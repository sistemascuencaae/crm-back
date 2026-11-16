<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Proformas (V04): un aporte de un producto de la proforma (ventas.dproforma, modelo Dproforma), uno por concepto
// APORTE: el concepto, de dónde salió (cabecera, detalle o parámetro), su cabecera y detalle de aporte, el orden, la vigencia y
// el valor. Se guarda con la proforma, todo o nada, y nunca se modifica. Modelo documental: lo escribe
// ventas.fn_cproforma_registrar y lo lee ventas.fn_cproforma_buscar (dentro de cada producto). 11-nov-2026 (Fase 9 del cotizador).
class DproformaAporte extends Model
{
    protected $table = 'ventas.dproforma_aporte';

    protected $fillable = [
        'dproforma_id',
        'cotizador_parametros_id',
        'concepto',
        'origen',
        'caporte_id',
        'cabecera',
        'daporte_id',
        'orden',
        'vigencia_inicio',
        'vigencia_fin',
        'valor_aporte',
    ];
}
