<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'public.cliente';

    protected $primaryKey = 'cli_id';

    protected $fillable = [
        "cli_id",
        "cli_codigo",
        "ent_id",
        "ubi_id",
        "zon_id",
        "cat_id",
        "cli_observacion",
        "cli_cupo",
        "pol_id",
        "lpr_id",
        "cli_impuesto",
        "cli_bloqueo",
        "cli_tarjeta",
        "cli_ilimitado",
        "cli_tipocli",
        "cli_activo",
        "locked",
        "emp_id",
        "can_id",
        "ent_nombre_comercial",
        "ent_representante_legal",
        "cli_tiposujeto",
        "cli_sexo",
        "cli_estadocivil",
        "cli_ingresos",
        "cli_parterel",
        "cli_codigosuplementario",
        "cli_actualizado",
        "reg_id",
        "cli_porc_retencion_iva",
        "cli_porc_retencion_fte",
        "cli_retencion",
        "cli_fecha_actualizacion",
        "cli_actualizado_verif",
        "cli_fecha_act_verif",
        "cli_bloqueo_sistema",
        "cli_fecha_bloqueo",
        "cli_direccion_servidor",
        "cli_num_comp_no_autorizados",
        "cli_num_comp_cnt_descuadrados",
        "cli_num_comp_cxc_descuadrados",
        "cli_num_comp_fac_descuadrados",
        "cli_num_comp_inv_descuadrados",
        "cli_fecha_ult_documento",
        "cli_autorizacion",
        "cli_img_cedula",
        "cli_img_croquis",
        "cli_hace_retencion",
        "emp_id_vendedor",
        "tcb_id",
        "cli_fecha_equifax",
        "cli_score",
        "cli_json_pdf",
    ];
}