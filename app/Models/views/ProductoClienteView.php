<?php

namespace App\Models\views;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoClienteView extends Model
{
    use HasFactory;

    protected $table = 'crm.crm_productos_cliente';
    protected $primaryKey = null;
    public $incrementing = false;
    protected $fillable = [
        "ent_id",
        "cli_id",
        "cfa_fecha",
        "comprobante",
        "cfa_facproveedor",
        "devuelto",
        "pro_nombre",
        "tpr_nombre",
        "cla1_nombre",
        "cfa_concepto",
        "dfac_cantidad",
        "dfac_costoprecio",
    ];

}
