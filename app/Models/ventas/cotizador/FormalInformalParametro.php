<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Formal / Informal: las reglas para ser FORMAL (línea, rango
// de edad y meses mínimos en la afiliación activa al IESS); quien no cumple ninguna es INFORMAL. Guardar, validar y
// auditar lo hacen las funciones ventas.fn_formal_informal_parametros_*: el modelo es la fuente de los campos.
class FormalInformalParametro extends Model
{
    protected $table = 'ventas.formal_informal_parametros';

    protected $fillable = [
        'linea',
        'edad_minima',
        'edad_maxima',
        'estabilidad_minima',
    ];
}
