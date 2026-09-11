<?php

namespace App\Models\crm\series;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RespaldoSeriesElim extends Model
{
    use SoftDeletes;
    use HasFactory;

    // Nombre de la tabla asociada
    protected $table = 'gex.res_serie_eliminada';

    // Definir los atributos que son asignables en masa
    protected $fillable = [
        'des_id',
        'cfa_id',
        'orden',
        'ubicacion',
        'fecha',
        'numero',
        'linea',
        'bod_id',
        'bod_egresa',
        'bod_actual',
        'responsable',
        'alm_id',
        'pro_id',
        'serie',
        'tipo',
        'doc_rela',
        'usuario_crea',
        'user_id_elimina',
        'user_id'
    ];

    // Definir los atributos que deben tratarse como fechas
    protected $dates = ['fecha', 'created_at', 'updated_at', 'deleted_at'];
}
