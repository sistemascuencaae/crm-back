<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Tarjetas y comisiones (V08): un plazo de una tarjeta: su tipo (ventas.tipo_tarjeta), los meses y los meses de gracia
// (únicos en la tarjeta), su comisión (vacía = la de la tarjeta o la del banco) y si está activo. Viaja dentro de su tarjeta
// ('plazos'): sin id es nuevo y con id es uno de esa tarjeta (la función lo verifica); tarjeta_id lo pone la función. Nunca se
// borra: se desactiva. Guardar y validar lo hacen ventas.fn_tarjeta_banco_*: el modelo es la fuente de los campos que manda la
// pantalla. 11-nov-2026 (Fase 7 del cotizador).
class TarjetaPlazo extends Model
{
    protected $table = 'ventas.tarjeta_plazo';

    protected $fillable = [
        'tipo_tarjeta_id',
        'plazo',
        'meses_gracia',
        'comision_banco',
        'activo',
    ];
}
