<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Proformas (V04): la cabecera de cada proforma guardada desde el requerimiento Proformar de un caso. Es la foto de
// ese Guardar (vendedor, punto de venta, región, cliente, IVA, política, calificación, márgenes, tasas, totales, pago con
// tarjeta o forma de pago de contado, Tipo cliente, actividad y comportamiento): cada Guardar es una versión nueva, nunca se
// modifica ni se borra (sin trigger de auditoría, excepción del 26-oct). Sus productos son ventas.dproforma (modelo Dproforma)
// y los aportes de cada producto ventas.dproforma_aporte (modelo DproformaAporte). Modelo documental: la foto la calcula y la
// guarda ventas.fn_cproforma_registrar a partir del pedido de Cotizar (no se arma con getFillable), y CproformaController la
// lee con las funciones ventas.fn_cproforma_*. 11-nov-2026 (Fase 9 del cotizador).
class Cproforma extends Model
{
    protected $table = 'ventas.cproforma';

    protected $fillable = [
        'tipo',
        'fecha',
        'user_id',
        'usuario_nombre',
        'pve_id',
        'punto_venta',
        'reg_id',
        'region',
        'cli_id',
        'cliente_identificacion',
        'cliente_nombre',
        'imp_id',
        'impuesto',
        'iva_porcentaje',
        'pol_id',
        'politica',
        'plazo',
        'cuotas_gratis',
        'calificacion_id',
        'calificacion',
        'margen_financiero_general',
        'incremento',
        'margen_financiero',
        'tasa_anual',
        'tasa_mensual',
        'entrada',
        'subtotal',
        'iva',
        'total_factura',
        'total_credito',
        'financiado',
        'valor_cuota',
        'total_a_pagar',
        'total_factura_lista',
        'caso_id',
        'tipo_cliente',
        'ban_id',
        'banco',
        'tarjeta_id',
        'tarjeta',
        'tipo_tarjeta',
        'tarjeta_plazo_id',
        'plazo_default_id',
        'meses_gracia',
        'contado_parametros_id',
        'forma_pago',
        'descuento',
        'origen_descuento',
        'total_sin_descuento',
        'valor_descuento',
        'comision_banco',
        'valor_comision',
        'actividad_cliente',
        'comportamiento_cliente',
    ];
}
