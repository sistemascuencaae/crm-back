<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvCasoCliente extends Model
{
    use HasFactory;


    protected $table = 'crm.av_caso_cliente';
    protected $fillable = [
        "id",
        "caso_nombre",
        "estado_2",
        "prioridad",
        "created_at",
        "fecha_vencimiento",
        "user_id",
        "usu_nombre",
        "usu_alias",
        "usu_tipo",
        "ent_id",
        "ent_identificacion",
        "nombre_cliente",
        "fas_id",
        "fas_nombre",
        "tab_id",
        "tab_nombre",
        "dep_id",
        "dep_nombre",
    ];

}
