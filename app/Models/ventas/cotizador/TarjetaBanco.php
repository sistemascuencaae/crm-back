<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Tarjetas y comisiones (V08): la comisión % y el descuento predeterminado % de un banco de Dynamo (public.banco),
// lo que heredan sus tarjetas y sus plazos sin valor propio (vacío = sin valor). Una fila por banco: su llave es ban_id (no
// tiene columna id ni autoincremento) y viene en la URL. Sus tarjetas (ventas.tarjeta, modelo Tarjeta) viajan dentro del banco
// ('tarjetas'), cada una con sus plazos (ventas.tarjeta_plazo, modelo TarjetaPlazo), y se guardan juntas con un solo Guardar.
// Guardar, validar y auditar lo hacen las funciones ventas.fn_tarjeta_banco_*: el modelo es la fuente de los campos que manda
// la pantalla. 11-nov-2026 (Fase 7 del cotizador).
class TarjetaBanco extends Model
{
    protected $table = 'ventas.tarjeta_banco';

    protected $primaryKey = 'ban_id';

    public $incrementing = false;

    protected $fillable = [
        'comision_banco',
        'descuento_empresa',
    ];
}
