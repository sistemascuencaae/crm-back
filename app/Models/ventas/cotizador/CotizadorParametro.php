<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Ventas > Parámetros cotizador (V02): los topes de cada concepto del cotizador (MARGEN, APORTE o TARJETA). Un concepto
// TARJETA es el default de un plazo de tarjeta: lleva el tipo de tarjeta, el plazo y los meses de gracia, y su valor_margen
// es el descuento por defecto. Guardar, validar y auditar lo hacen las funciones ventas.fn_cotizador_parametros_*: el
// modelo es la fuente de los campos que manda la pantalla.
class CotizadorParametro extends Model
{
    protected $table = 'ventas.cotizador_parametros';

    protected $fillable = [
        'nombre',
        'tipo_id',
        'minimo',
        'maximo',
        'valor_margen',
        'tipo_tarjeta_id',
        'plazo',
        'meses_gracia',
    ];
}
