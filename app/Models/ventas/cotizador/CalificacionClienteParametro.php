<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Ventas > Parámetros de calificación (V05): la calificación del cliente en cada política de clientes (tipo de cliente +
// Formal/Informal + comportamiento, única en la política) con su margen financiero general y su incremento. margen_financiero
// es una columna generada (general + incremento): no se escribe. Guardar, validar, clonar y auditar lo hacen las funciones
// ventas.fn_calificacion_cliente_parametros_*: el modelo es la fuente de los campos que manda la pantalla (pol_id va en la URL).
class CalificacionClienteParametro extends Model
{
    protected $table = 'ventas.calificacion_cliente_parametros';

    protected $fillable = [
        'pol_id',
        'tipo_cliente_id',
        'actividad_cliente_id',
        'comportamiento_cliente_id',
        'predeterminado',
        'margen_financiero_general',
        'incremento',
        'activo',
    ];
}
