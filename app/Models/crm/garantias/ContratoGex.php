<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ContratoGex extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.contrato_gex';

    protected $primaryKey = 'alm_id, numero';

    public $timestamps = false;

    protected $fillable = [
        "alm_id",
        "numero",
        "fecha",
        "nom_almacen",
        "nom_cliente",
        "tipo_identificacion",
        "identificacion",
        "provincia",
        "ciudad",
        "direccion",
        "telefono",
        "celular",
        "email",
        "pro_id",
        "producto",
        "cfa_id",
        "factura",
        "fecha_compra",
        "marca",
        "num_despacho",
        "serie",
        "garantia_marca",
        "ubicacion",
        "cfa_id_gex",
        "factura_gex",
        "meses_gex",
        "fecha_desde",
        "fecha_hasta",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
        "km_garantia",
        "km_factor",
        "tipo_servicio",
    ];
}