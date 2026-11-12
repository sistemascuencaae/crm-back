<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Tipo de cliente: el rango de score de cada tipo de cliente.
// 10-nov noche: + tipo_cliente (NUEVO o REITERATIVO).
// 11-nov-2026 (pestaña 2): sale acreedores (SI/NO); van los 3 cortes de acreedores (Actual, -6 Meses y -12 Meses, cada uno con
// mínimo y máximo), las 4 fechas del Excel (apertura SBS, RFR y SICOM, y consulta) y activo.
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
        // QUITADO 11-nov-2026 (pestaña 2): 'acreedores',
        'acreedores_actual_minimo',
        'acreedores_actual_maximo',
        'acreedores_6_meses_minimo',
        'acreedores_6_meses_maximo',
        'acreedores_12_meses_minimo',
        'acreedores_12_meses_maximo',
        'garancheck_nn',
        'garancheck_tc',
        'fecha_apertura_sbs',
        'fecha_apertura_rfr',
        'fecha_apertura_sicom',
        'fecha_consulta',
        'antiguedad_minima',
        'antiguedad_maxima',
        'perfil_riesgo',
        'activo',
    ];
}
