<?php

namespace App\Models\contabilidad;

use Illuminate\Database\Eloquent\Model;

// Version 1.0
// CONTABILIDAD > Sistemas de liquidación (menú SISTEMAS-LIQUIDACION): donde liquida cada emisor (public.sistema_liquidacion, la
// tabla nuestra, con las 4 columnas de auditoría). Nunca se borra: se desactiva (no si lo usa un emisor activo). Guardar,
// validar y auditar lo hace ventas.fn_sistema_liquidacion_guardar (PLANES/COTIZADOR/produccion_bancos_emisores.sql), que
// también llena created_*/updated_*: el modelo no carga filas, es la fuente de los campos que manda la pantalla.
// 11-nov-2026 (reestructura de Contabilidad).
class SistemaLiquidacion extends Model
{
    protected $table = 'public.sistema_liquidacion';

    protected $primaryKey = 'sli_id';

    public $timestamps = false;

    // Las columnas que cambia la pantalla.
    protected $fillable = [
        'sli_nombre',
        'sli_activo',
    ];

    // La clave de cada columna en el jsonb que recibe ventas.fn_sistema_liquidacion_guardar (las de siempre: la función no
    // cambió). sli_id no va aquí: al editar viene en la URL y al agregar es null.
    public const CLAVES_FUNCION = [
        'sli_nombre' => 'nombre',
        'sli_activo' => 'activo',
    ];
}
