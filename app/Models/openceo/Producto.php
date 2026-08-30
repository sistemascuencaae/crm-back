<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'public.producto';
    use HasFactory;
    protected $primaryKey = 'pro_id';

    protected $fillable = [
        "pro_id",
        "pro_codigo",
        "pro_nombre",
        "pro_stockmin",
        "pro_stockmax",
        "pro_peso",
        "pro_envase",
        "uni_id",
        "pro_comision",
        "uinv_id",
        "pro_beneficio",
        "pro_ultcostocompra",
        "pro_ultcostoprome",
        "pro_observacion",
        "pro_imagen",
        "pro_codbarras",
        "pro_pesobruto",
        "pro_impuesto",
        "pro_inventario",
        "pro_activo",
        "tpr_id",
        "locked",
        "mar_id",
        "ser_id",
        "ubi_id",
        "pro_serie",
        "pro_inv_codigo",
        "pro_costofob",
        "pro_observacion_factura",
        "obs_id",
        "pro_obj_impuesto",
        "pro_lote",
        "lot_id",
        "cla1_id",
        "pro_nuevo",
        "pro_cantidad_min_factura",
        "pro_tipo_componente",
        "cpr_id",
        "pro_ancho",
        "pro_largo",
        "pro_receta",
        "pro_internacional",
        "pro_generacion",
        "pro_tipo_disco",
        "pro_cod_paquete",
        "pro_prioridad_paquete",
        "pro_abreviacion",
        "pro_ecovalor",
        "pro_equivalencia",
        "pro_costo_preciador",
    ];
}
