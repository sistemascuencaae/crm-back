<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Version 1.0
// Cotizador > Parámetros tipo calificación cliente, pestaña Performance crédito, panel izquierdo "Líneas": el catálogo de
// líneas (ventas.linea; arranca con ELECTRO y MOTO) que comparten Performance crédito, Formal / Informal y el requerimiento
// 'formal informal'. Nunca se borra: se desactiva. Guardar, validar (nombre obligatorio, hasta 50, en mayúsculas, único) y
// auditar lo hacen las funciones ventas.fn_linea_* (PLANES/COTIZADOR/produccion_linea.sql), que también llenan
// created_*/updated_*: el modelo no carga filas, es la fuente de los campos que manda la pantalla.
// 11-nov-2026 (líneas).
class Linea extends Model
{
    protected $table = 'ventas.linea';

    protected $fillable = [
        'nombre',
        'activo',
    ];
}
