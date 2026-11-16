<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Proformas (V04): un producto de una proforma guardada (cabecera ventas.cproforma, modelo Cproforma): código,
// nombre, cantidad, costo y su origen, aportes y CAME; el margen con su origen y vigencia; la negociación (precios de lista,
// mínimo, máximo y negociado), el precio sin descuento y los valores por unidad y de la línea. Sus aportes son
// ventas.dproforma_aporte (modelo DproformaAporte). Se guarda con su cabecera, todo o nada, y nunca se modifica. Modelo
// documental: lo escribe ventas.fn_cproforma_registrar y lo lee ventas.fn_cproforma_buscar (con la cabecera).
// 11-nov-2026 (Fase 9 del cotizador).
class Dproforma extends Model
{
    protected $table = 'ventas.dproforma';

    protected $fillable = [
        'cproforma_id',
        'pro_id',
        'pro_codigo',
        'pro_nombre',
        'cantidad',
        'costo',
        'graba_iva',
        'total_aportes',
        'came',
        'cotizador_parametros_id',
        'concepto_margen',
        'origen_margen',
        'cmargen_id',
        'cabecera_margen',
        'dmargen_id',
        'orden_margen',
        'vigencia_inicio_margen',
        'vigencia_fin_margen',
        'valor_margen',
        'minimo_negociacion',
        'maximo_negociacion',
        'subtotal_unitario',
        'iva_unitario',
        'total_unitario',
        'total_credito_unitario',
        'cuota_unitaria',
        'subtotal',
        'iva',
        'total',
        'total_credito',
        'valor_margen_lista',
        'precio_lista_unitario',
        'precio_minimo_unitario',
        'precio_maximo_unitario',
        'negociado',
        'origen_costo',
        'precio_sin_descuento_unitario',
    ];
}
