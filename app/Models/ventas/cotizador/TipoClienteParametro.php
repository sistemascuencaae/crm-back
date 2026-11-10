<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Tipo de cliente: el rango de score de cada tipo de cliente.
// Guardar, validar y auditar lo hacen las funciones ventas.fn_tipo_cliente_parametros_*: el modelo es la fuente de los
// campos.
class TipoClienteParametro extends Model
{
    protected $table = 'ventas.tipo_cliente_parametros';

    protected $fillable = [
        'nombre',
        'tipo',
        'score_minimo',
        'score_maximo',
    ];
}
