<?php

namespace App\Models\ventas\cotizador;

use Illuminate\Database\Eloquent\Model;

// Cotizador > Parámetros tipo calificación cliente, pestaña Tipo de cliente: el rango de score de cada tipo de cliente.
// 10-nov noche: + tipo_cliente (NUEVO o REITERATIVO).
// 11-nov-2026 (pestaña 2): sale acreedores (SI/NO); van los 3 cortes de acreedores (Actual, -6 Meses y -12 Meses, cada uno con
// mínimo y máximo), las 4 fechas del Excel (apertura SBS, RFR y SICOM, y consulta) y activo.
// 12-nov-2026 (booleanos): garancheck_nn y garancheck_tc son boolean (checks); salen los 3 cortes de acreedores y las 4 fechas.
// 13-nov-2026 (antiguedad): una sola antiguedad numérica. 13-nov-2026 (nn texto): garancheck_nn es texto en MAYÚSCULAS o null.
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
        // QUITADO 12-nov-2026 (booleanos): 'acreedores_actual_minimo',
        // QUITADO 12-nov-2026 (booleanos): 'acreedores_actual_maximo',
        // QUITADO 12-nov-2026 (booleanos): 'acreedores_6_meses_minimo',
        // QUITADO 12-nov-2026 (booleanos): 'acreedores_6_meses_maximo',
        // QUITADO 12-nov-2026 (booleanos): 'acreedores_12_meses_minimo',
        // QUITADO 12-nov-2026 (booleanos): 'acreedores_12_meses_maximo',
        'garancheck_nn',
        'garancheck_tc',
        // QUITADO 12-nov-2026 (booleanos): 'fecha_apertura_sbs',
        // QUITADO 12-nov-2026 (booleanos): 'fecha_apertura_rfr',
        // QUITADO 12-nov-2026 (booleanos): 'fecha_apertura_sicom',
        // QUITADO 12-nov-2026 (booleanos): 'fecha_consulta',
        // QUITADO 13-nov-2026 (antiguedad): 'antiguedad_minima',
        // QUITADO 13-nov-2026 (antiguedad): 'antiguedad_maxima',
        // 13-nov-2026 (antiguedad): una sola columna numérica (solo el parámetro; quien la lee decide cómo compararla).
        'antiguedad',
        'perfil_riesgo',
        'activo',
    ];
}
