<?php

namespace App\Models\contabilidad;

use Illuminate\Database\Eloquent\Model;

// Version 1.0
// CONTABILIDAD > Bancos (menú BANCOS): un banco de Dynamo (public.banco, también los inactivos), con su número de cuenta, su
// cuenta contable de Bancos (la crea Contabilidad, una por banco) y si es comercial (los que se ofrecen en ventas). Nunca se
// borra: se desactiva. Los que Dynamo tiene en edición (locked) no se cambian. Guardar (uno nuevo, con su dirección), validar y
// auditar lo hace ventas.fn_banco_guardar (PLANES/COTIZADOR/produccion_bancos_emisores.sql): el modelo no carga filas, es la
// fuente de los campos que manda la pantalla. 11-nov-2026 (reestructura de Contabilidad).
// 27-nov (pedido del usuario): también el cheque actual y los programas de impresión de cheques y de pagos.
class Banco extends Model
{
    protected $table = 'public.banco';

    protected $primaryKey = 'ban_id';

    public $timestamps = false;

    // Las columnas que cambia la pantalla (las demás son de Dynamo).
    protected $fillable = [
        'ban_nombre',
        'ban_cuenta',
        'cco_id',
        'ban_activo',
        'ban_comercial',
        // 27-nov (pedido del usuario): el cheque actual y los programas de impresión de cheques y de pagos.
        'ban_chequeactual',
        'pgr_id',
        'pgr_pag_id',
    ];

    // La clave de cada columna en el jsonb que recibe ventas.fn_banco_guardar (las de siempre: la función no cambió). ban_id no
    // va aquí: al editar viene en la URL y al agregar es null.
    // 27-nov: más las 3 de Dynamo que faltaban; la dirección va aparte (Direccion::CLAVES_FUNCION_BANCO).
    public const CLAVES_FUNCION = [
        'ban_nombre' => 'nombre',
        'ban_cuenta' => 'cuenta',
        'cco_id' => 'cco_id',
        'ban_activo' => 'activo',
        'ban_comercial' => 'comercial',
        'ban_chequeactual' => 'cheque_actual',
        'pgr_id' => 'pgr_id',
        'pgr_pag_id' => 'pgr_pag_id',
    ];
}
