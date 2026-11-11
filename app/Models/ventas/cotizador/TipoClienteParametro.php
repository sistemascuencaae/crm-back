<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Tipo de cliente: el rango de score de cada tipo de cliente.
// 10-nov noche: + tipo_cliente (NUEVO o REITERATIVO).
// Guardar, validar y auditar lo hacen las funciones ventas.fn_tipo_cliente_parametros_*: el modelo es la fuente de los
// campos.
class TipoClienteParametro extends Model
{
    protected $table = 'ventas.tipo_cliente_parametros';

    protected $fillable = [
        'nombre',
        'tipo_cliente',
        'score_minimo',
        'score_maximo',
        'edad_minima',
        'edad_maxima',
        'acreedores',
        'garancheck_nn',
        'garancheck_tc',
        'antiguedad_minima',
        'antiguedad_maxima',
        'perfil_riesgo',
    ];
}
