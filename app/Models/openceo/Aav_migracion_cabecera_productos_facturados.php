<?php

namespace App\Models\openceo;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aav_migracion_cabecera_productos_facturados extends Model
{

    use HasFactory;

    protected $table = 'public.aav_migracion_cabecera_productos_facturados';
    protected $fillable = [
        "fecha_reporte",
        "cfa_id",
        "ccm_id",
        "tipo_comprobante_fp",
        "cod_comprobante_fp",
        "comprobante2",
        "ddo_doctran",
        "periodo",
        "mes",
        "fecha",
        "forma_pago",
        "subtotal",
        "base_imponible",
        "base_excenta",
        "descuento",
        "por_iva",
        "iva",
        "total",
        "financiamiento",
        "numero_pagos",
        "valor",
        "valor_cancelado",
        "saldo",
        "estado",
        "cod_persona",
        "nombre_persona",
        "codigo_almacen",
        "nombre_almacen",
        "pve_numero",
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

    public function detalle()
    {
        return $this->hasMany(Aav_migracion_detalle_productos_facturados::class, "cfa_id", "cfa_id");
    }

}
