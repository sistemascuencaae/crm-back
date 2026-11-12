<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Comportamiento: los valores que recibe
// ventas.fn_score_cliente_calificacion. Guardar, validar y auditar lo hacen las funciones
// ventas.fn_comportamiento_cliente_parametros_*: el modelo es la fuente de los campos.
// 11-nov-2026 (activo): + activo (lo guardado ya no se borra: se desactiva). Solo UNA fila puede estar activa.
class ComportamientoClienteParametro extends Model
{
    protected $table = 'ventas.comportamiento_cliente_parametros';

    protected $fillable = [
        'nombre',
        'estado_credito',
        'meses_factura',
        'participacion_cuotas_pagas_con_rec',
        'tiempo_sin_operacion',
        'numero_cuotas',
        'dias_atraso_oro',
        'dias_atraso_plata',
        'dias_atraso_bronce',
        'porcentaje_oro',
        'porcentaje_plata',
        'porcentaje_bronce',
        'activo',
    ];
}
