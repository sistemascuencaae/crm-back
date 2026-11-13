<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Performance crédito: la hoja "Actual" del Excel de Scoring 2026
// hecha parámetro. Una fila por línea (ELECTRO o MOTO) y tipo de cliente (QUITADO 12-nov-2026 (tipo cliente de la 2): las N, NN y
// R de ventas.tipo_cliente; 12-nov-2026: una regla de la pestaña 2 Tipo de cliente, ventas.tipo_cliente_parametros; tipo_cliente_id
// es el id de la regla): si aplica crédito y, para Formal e Informal, la capacidad de pago, la inicial (en % o en $ según
// inicial_tipo), el plazo en meses y la inspección. Guardar, validar y auditar lo hacen las funciones
// ventas.fn_performance_credito_*: el modelo es la fuente de los campos.
// 11-nov-2026 (líneas): la línea es linea_id (FK al catálogo ventas.linea), ya no el texto.
// 11-nov-2026 (activo): + activo (lo guardado ya no se borra: se desactiva; una fila inactiva no la devuelve
// fn_performance_credito_condiciones_buscar).
// 12-nov-2026 (booleanos): aplica es boolean (antes el texto 'SI' / 'NO'); el campo no cambia de nombre.
class PerformanceCredito extends Model
{
    protected $table = 'ventas.performance_credito';

    protected $fillable = [
        // QUITADO 11-nov-2026 (líneas): 'linea',
        'linea_id',
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
        'activo',
    ];
}
