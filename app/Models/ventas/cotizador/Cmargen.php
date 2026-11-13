<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Ventas > Margen y aportes, pestaña Margen (V01): la cabecera del margen, un valor por región y concepto (de tipo MARGEN en
// ventas.cotizador_parametros) con su rango de negociación. Sus detalles (ventas.dmargen, modelo Dmargen) y sus productos
// (ventas.cmargen_productos, modelo CmargenProducto) se guardan con ella, todo con un solo Guardar. Guardar, validar y
// auditar lo hacen las funciones ventas.fn_cmargen_*: el modelo es la fuente de los campos que manda la pantalla.
// 11-nov-2026 (Fase 2 del cotizador).
class Cmargen extends Model
{
    protected $table = 'ventas.cmargen';

    protected $fillable = [
        'cotizador_parametros_id',
        'reg_id',
        'nombre',
        'valor_margen',
        'minimo_negociacion',
        'maximo_negociacion',
        'activo',
    ];
}
