<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Tarjetas y comisiones (V08): una tarjeta de un banco, con su nombre (único en el banco), su emisor (public.emisor),
// su comisión y su descuento (vacíos = los del banco) y si está activa. Viaja dentro del banco ('tarjetas'): sin id es nueva y
// con id es una de ese banco (la función lo verifica); ban_id lo pone la función (viene en la URL). Sus plazos (modelo
// TarjetaPlazo) viajan dentro de ella ('plazos'). Nunca se borra: se desactiva. Guardar y validar lo hacen
// ventas.fn_tarjeta_banco_*: el modelo es la fuente de los campos que manda la pantalla. 11-nov-2026 (Fase 7 del cotizador).
class Tarjeta extends Model
{
    protected $table = 'ventas.tarjeta';

    protected $fillable = [
        'nombre',
        'emi_id',
        'comision_banco',
        'descuento_empresa',
        'activo',
    ];
}
