<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioDynamo extends Model
{
    use HasFactory;

    protected $table = 'public.usuario';

    protected $primaryKey = 'usu_id';

    public $timestamps = false;

    protected $fillable = [
        "usu_nombre",
        "usu_apellido",
        "usu_alias",
        "usu_contrasena",
        "usu_rol",
        "usu_activo",
        "cme_id",
        "usu_vigencia_desde",
        "usu_vigencia_hasta",
        "locked",
        "pve_id",
        "usu_ip",
        "usu_administrador",
        "idi_id",
        "usu_tipo",
        "usu_tlf",
        "usu_base",
        "usu_alias_completo",
        "pti_id",
        "usu_gestion",
        "usu_bloqueo",
        "usu_mostrar_bloqueo",
        "usu_habilitado",
        "usu_num_gestiones",
        "usu_tipo_analista",
    ];
}