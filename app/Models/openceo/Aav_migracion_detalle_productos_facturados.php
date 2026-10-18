<?php

namespace App\Models\openceo;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aav_migracion_detalle_productos_facturados extends Model
{

    use HasFactory;

    protected $table = 'public.aav_migracion_detalle_productos_facturados';
    protected $fillable = [
        "cfa_id",
        "pro_id",
        "pro_codigo",
        "pro_nombre",
        "cantidad",
        "bod_codigo",
        "bod_nombre",
        "es_iva",
        "precio_unitario",
        "descporcitem",
        "detalle_subtotal",
        "detalle_descuentos",
        "ajuste",
        "transporte",
        "detalle_total_sin_impuesto",
        "valor_iva",
        "porcentaje_iva",
        "baseimponible_iva",
        "detalle_total",
        "costo_promedio",
    ];

    // public function setCreatedAtAttribute($value)
    // {
    //     date_default_timezone_set("America/Guayaquil");
    //     $this->attributes["created_at"] = Carbon::now();
    // }
    // public function setUpdatedAtAttribute($value)
    // {
    //     date_default_timezone_set("America/Guayaquil");
    //     $this->attributes["updated_at"] = Carbon::now();
    // }
    // public function setDeletedAtAttribute($value)
    // {
    //     date_default_timezone_set("America/Guayaquil");
    //     $this->attributes["deleted_at"] = Carbon::now();
    // }
}
