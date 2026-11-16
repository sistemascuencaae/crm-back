<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Ventas > Margen y aportes, pestaña Margen (V01): un producto de una cabecera de margen. Un producto va en una sola
// cabecera por región y concepto. La pantalla manda la lista final de pro_id de la cabecera ('productos') con el mismo
// Guardar; cmargen_id lo pone la función. ventas.fn_cmargen_productos_guardar_lote deja la tabla igual a esa lista y
// registra lo asignado y lo quitado. 11-nov-2026 (Fase 2 del cotizador).
class CmargenProducto extends Model
{
    protected $table = 'ventas.cmargen_productos';

    protected $fillable = [
        'pro_id',
    ];
}
