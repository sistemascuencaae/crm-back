<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Formal / Informal: las reglas para ser FORMAL (rango de edad
// y meses mínimos en la afiliación activa al IESS); quien no cumple ninguna es INFORMAL. Guardar, validar y
// auditar lo hacen las funciones ventas.fn_formal_informal_parametros_*: el modelo es la fuente de los campos.
// 11-nov-2026 (activo): + activo (lo guardado ya no se borra: se desactiva; una regla inactiva no cuenta).
// 16-nov-2026 (sin línea): la regla ya no tiene línea (la columna linea_id salió de la tabla).
class FormalInformalParametro extends Model
{
    protected $table = 'ventas.formal_informal_parametros';

    protected $fillable = [
        // QUITADO 11-nov-2026 (líneas): 'linea',
        // QUITADO 16-nov-2026 (sin línea): 'linea_id',
        'edad_minima',
        'edad_maxima',
        'estabilidad_minima',
        'activo',
    ];
}
