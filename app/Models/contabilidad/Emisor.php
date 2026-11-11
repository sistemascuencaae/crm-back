<?php

namespace App\Models\contabilidad;

use Illuminate\Database\Eloquent\Model;

// Version 1.0
// CONTABILIDAD > Emisores (menú EMISORES): un emisor de tarjetas de Dynamo (public.emisor; antes, los adquirentes del
// cotizador): quien procesa el cobro de la tarjeta, con su POS y su sistema de liquidación (public.sistema_liquidacion). Nunca
// se borra: se desactiva. Los que Dynamo tiene en edición (locked) no se cambian. Guardar, validar y auditar lo hace
// ventas.fn_emisor_guardar (PLANES/COTIZADOR/produccion_bancos_emisores.sql): el modelo no carga filas, es la fuente de los
// campos que manda la pantalla. 11-nov-2026 (reestructura de Contabilidad).
class Emisor extends Model
{
    protected $table = 'public.emisor';

    protected $primaryKey = 'emi_id';

    public $timestamps = false;

    // Las columnas que cambia la pantalla (emi_codigo y locked son de Dynamo).
    protected $fillable = [
        'emi_nombre',
        'emi_pos',
        'sli_id',
        'emi_activo',
    ];

    // La clave de cada columna en el jsonb que recibe ventas.fn_emisor_guardar (las de siempre: la función no cambió). emi_id no
    // va aquí: al editar viene en la URL y al agregar es null.
    public const CLAVES_FUNCION = [
        'emi_nombre' => 'nombre',
        'emi_pos' => 'pos',
        'sli_id' => 'sli_id',
        'emi_activo' => 'activo',
    ];
}
