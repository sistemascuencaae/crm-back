<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CPedidoProforma extends Model
{
    use HasFactory;
    protected $table = 'crm.cpedido_movil';
    protected $primaryKey = 'cpp_id';
    protected $fillable = [
        "cpp_fecha",
        "alm_nombre",
        "cpp_concepto",
        "cli_id",
        "ent_nombre_comercial",
        "ent_email",
        "empleado",
        "comprobante",
        "sub_total_compra",
        "total_compra",
        "cpp_estado",
        "cpp_entrada",
        "cpp_entradaadicional",
        "cpp_contraentrega",
        "cpp_cuotas",
        "cpp_cuotas_gratis",
        "cpp_valor_cuota",
        "pol_id",
        "bod_id",
        "nombre_bodega",
        "cpp_fecha_subido",
        "pve_id",
        "nombre_punto_venta",
        "nombre_almacen",
    ];
    public function dpedidoProforma()
    {
        return $this->hasMany(DPedidoProforma::class, "cpp_id");
    }
}
