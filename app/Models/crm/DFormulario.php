<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DFormulario extends Model
{

    protected $table = 'crm.dformulario';

    protected $fillable = [
        "id",
        "cform_id",
        "titulo",
        "tipo_campo",
        "requerido",
        "estado",
        "created_at",
        "updated_at",
        "deleted_at",
        "valor_date",
        "valor_int",
        "valor_boolean",
        "valor_varchar",
        "valor_decimal",
        "html_render",
        "valor",
        "form_control_name",
        "descripcion"

    ];


}
