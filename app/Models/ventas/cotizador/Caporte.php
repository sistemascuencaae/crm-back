<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Ventas > Margen y aportes, pestaña Aportes (V01): la cabecera del aporte, un valor en dólares por región y concepto (de tipo
// APORTE en ventas.cotizador_parametros), sin rango de negociación (el rango va en cada detalle). Sus detalles (ventas.daporte,
// modelo Daporte) y sus productos (ventas.caporte_productos, modelo CaporteProducto) se guardan con ella, todo con un solo
// Guardar. Guardar, validar y auditar lo hacen las funciones ventas.fn_caporte_*: el modelo es la fuente de los campos que manda
// la pantalla. 11-nov-2026 (Fase 3 del cotizador).
class Caporte extends Model
{
    protected $table = 'ventas.caporte';

    protected $fillable = [
        'cotizador_parametros_id',
        'reg_id',
        'nombre',
        'valor_aporte',
        'activo',
    ];
}
