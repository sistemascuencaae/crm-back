<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Contado (V07): una regla de una forma de pago de contado. Dentro de su vigencia (fecha y hora) y en los días
// marcados reemplaza al descuento de la forma; si dos aplican a la vez gana la de menor orden. Viaja dentro de la forma
// ('reglas'): sin id es nueva y con id es una de esa forma (la función lo verifica); contado_parametros_id lo pone la
// función. Nunca se borra: se desactiva. Guardar y validar lo hacen ventas.fn_contado_parametros_* y
// ventas.fn_contado_reglas_*: el modelo es la fuente de los campos que manda la pantalla. 11-nov-2026 (Fase 6 del cotizador).
class ContadoRegla extends Model
{
    // Los días de la semana de la regla (columnas boolean); el que no viene cuenta como no marcado.
    public const DIAS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    protected $table = 'ventas.contado_reglas';

    protected $fillable = [
        'orden',
        'fecha_inicio',
        'fecha_fin',
        'lunes',
        'martes',
        'miercoles',
        'jueves',
        'viernes',
        'sabado',
        'domingo',
        'descuento',
        'activo',
    ];
}
