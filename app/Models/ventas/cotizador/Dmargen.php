<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Ventas > Margen y aportes, pestaña Margen (V01): un detalle (regla) de una cabecera de margen. Dentro de su vigencia y en
// los días marcados reemplaza al valor de la cabecera; el orden es la prioridad si dos aplican a la vez. Viaja dentro de la
// cabecera ('detalles'): sin id es nuevo y con id es uno de esa cabecera (la función lo verifica); cmargen_id lo pone la
// función. Nunca se borra: se desactiva. Guardar y validar lo hacen ventas.fn_cmargen_* y ventas.fn_dmargen_*: el modelo
// es la fuente de los campos que manda la pantalla. 11-nov-2026 (Fase 2 del cotizador).
class Dmargen extends Model
{
    // Los días de la semana del detalle (columnas boolean); el que no viene cuenta como no marcado.
    public const DIAS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    protected $table = 'ventas.dmargen';

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
        'minimo_negociacion',
        'maximo_negociacion',
        'valor_margen',
        'activo',
    ];
}
