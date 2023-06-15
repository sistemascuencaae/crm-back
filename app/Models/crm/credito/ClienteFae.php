<?php

namespace App\Models\crm\credito;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteFae extends Model
{
    use HasFactory;
    protected $table = 'public.av_clientes_reiterativos';

    public $timestamps = false;

    protected $fillable = [
        "ent_id",
        "cedula",
        "cliente",
        "fecha_nacimiento",
        "email",
        "telefonos_adicionales",
        "ciudad_domicilio",
        "direccion_principal",
        "ingresos",
        "egresos",
        "cedula_conyuge",
        "nombre_conyuge",
        "fecha_nacimiento_conyuge",
        "periodo_fae",
        "factura",
        "fecha_emision",
        "fecha_actual",
        "numero_cuota",
        "fecha_vencimiento_cuota",
        "monto_cuota",
        "monto_cancelado_cuota",
        "monto_pendiente_cuota",
        "estado_cuota",
        "numero_ultimo_recibo",
        "fecha_ultimo_recibo",
        "dias_atraso_recibo",
        "dias_atraso_alafecha",
        "alamcen",
        "punto_venta",
    ];


}





// select * from public.entidad ent inner join public.cliente cli on cli.ent_id = ent.ent_id