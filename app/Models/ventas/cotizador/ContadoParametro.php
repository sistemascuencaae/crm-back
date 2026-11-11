<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Contado (V07): una forma de pago de contado (EFECTIVO, TRANSFERENCIA...) con su descuento %, si es la
// predeterminada (una sola, y activa) y si está activa. Sus reglas (ventas.contado_reglas, modelo ContadoRegla) viajan dentro
// de la forma ('reglas') y se guardan con ella: la pantalla manda todas las formas nuevas y cambiadas en un solo Guardar.
// Nunca se borra: se desactiva. Guardar, validar y auditar lo hacen las funciones ventas.fn_contado_parametros_*: el modelo
// es la fuente de los campos que manda la pantalla. 11-nov-2026 (Fase 6 del cotizador).
class ContadoParametro extends Model
{
    protected $table = 'ventas.contado_parametros';

    protected $fillable = [
        'nombre',
        'descuento',
        'predeterminado',
        'activo',
    ];
}
