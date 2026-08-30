<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoProducto extends Model
{
    protected $table = 'public.tipo_producto';
    use HasFactory;
    protected $primaryKey = 'tpr_id';

    protected $fillable = [
        'tpr_nombre',
        'tpr_nivel',
        'tpr_orden',
        'tpr_reporta',
        'cco_id_inv',
        'cco_id_ing',
        'cco_id_cos',
        'cco_id_desctos_vtas',
        'cco_id_dev_com',
        'locked',
        'tpr_pordefecto',
        'cco_id_dev_vtas',
        'tpr_codigo',
        'tpr_activo',
        'tpr_movimiento',
        'tpr_prioridad',
        'cco_id_dev_com_mal_est',
        'cco_id_dev_vtas_mal_est',
        'tpr_porc_comision',
        'tpr_porc_incentivo',
        'ubinv_id',
        'tpr_porc_utilidad'
    ];
    public function subTipos()
    {
        return $this->hasMany(TipoProducto::class, 'tpr_reporta', 'tpr_id');
    }
}
