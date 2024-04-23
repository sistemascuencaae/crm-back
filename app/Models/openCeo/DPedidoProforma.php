<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DPedidoProforma extends Model
{
    use HasFactory;
    protected $table = 'crm.dpedido_movil';
    protected $primaryKey = 'dpp_id';
     protected $fillable = [
        "tipo",
        "pro_nombre",
        "pro_id",
        "pro_codigo",
        "marca",
        "mar_id",
        "dpp_valortotal",
        "dpp_valor_cuota",
        "dpp_tipoentrega",
        "dpp_entrada",
        "dpp_costoprecio",
        "dpp_costo_preciador",
        "dpp_cantidad",
        "cpp_id",
     ];
}
