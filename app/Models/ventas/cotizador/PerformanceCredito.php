<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Performance crédito: la hoja "Actual" del Excel de Scoring 2026
// hecha parámetro. Una fila por línea (ELECTRO o MOTO) y tipo de cliente (las N, NN y R de ventas.tipo_cliente): si aplica
// crédito y, para Formal e Informal, la capacidad de pago, la inicial (en % o en $ según inicial_tipo), el plazo en meses y
// la inspección. Guardar, validar y auditar lo hacen las funciones ventas.fn_performance_credito_*: el modelo es la fuente
// de los campos.
class PerformanceCredito extends Model
{
    protected $table = 'ventas.performance_credito';

    protected $fillable = [
        'linea',
        'tipo_cliente_id',
        'aplica',
        'capacidad_pago_formal',
        'capacidad_pago_informal',
        'inicial_tipo',
        'inicial_formal',
        'inicial_informal',
        'plazo_formal',
        'plazo_informal',
        'inspeccion_formal',
        'inspeccion_informal',
    ];
}
