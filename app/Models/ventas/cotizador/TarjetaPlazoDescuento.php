<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Descuentos de plazos (V09): un descuento de un plazo de tarjeta (ventas.tarjeta_plazo_descuento). Sin fechas es el
// descuento fijo del plazo (uno activo por plazo); con fecha y hora de inicio y de fin, una promoción (las activas de un plazo no
// se cruzan). Viaja dentro de su plazo ('promociones' de cada {tarjeta_plazo_id, fijo}): sin id es nueva y con id es una de ese
// plazo (la función lo verifica); tarjeta_plazo_id lo pone la función con el plazo en el que viaja. Nunca se borra: se desactiva.
// Guardar, validar y auditar lo hacen las funciones ventas.fn_tarjeta_plazo_descuento_*: el modelo es la fuente de los campos que
// manda la pantalla. 11-nov-2026 (Fase 8 del cotizador).
class TarjetaPlazoDescuento extends Model
{
    protected $table = 'ventas.tarjeta_plazo_descuento';

    protected $fillable = [
        'descuento_empresa',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];
}
