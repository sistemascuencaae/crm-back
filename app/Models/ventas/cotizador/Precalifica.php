<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Version 1.0
// Precalifica del caso (ventas.precalifica): una fila por línea de lo que devolvió ventas.fn_cliente_perfilamiento (de la otra
// persona, no se toca) para la cédula del caso. La guardan y la leen las funciones ventas.fn_precalifica_* (PLANES/COTIZADOR/
// produccion_precalifica.sql), que también llenan created_*/updated_*: el modelo no carga filas, es la fuente de los campos.
// es_vigente = la precalifica actual del caso; crm.caso.precalifica_id = la fila que eligió el TIPO CRÉDITO.
// 19-nov-2026.
class Precalifica extends Model
{
    protected $table = 'ventas.precalifica';

    protected $fillable = [
        'caso_id',
        'identificacion',
        'cli_id',
        'linea',
        'perfil',
        'actividad',
        'comportamiento',
        'cuota',
        'plazo',
        'porcentaje_inicial',
        'tiene_inicial',
        'es_reiterativo',
        'score',
        'riesgo',
        'es_vigente',
    ];
}
