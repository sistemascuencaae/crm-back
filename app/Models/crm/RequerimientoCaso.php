<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequerimientoCaso extends Model
{
    use HasFactory;
    protected $table = 'crm.requerimientos_caso';


    protected $fillable = [
       "descripcion",
       "caso_id",
       "created_at",
       "updated_at",
       "deleted_at",
       "marcado",
       "estado",
       "tipo_req_id",
       "user_requiere_id",
       "titulo",
       "tipo_campo",
       "requerido",
       "valor_date",
       "valor_int",
       "valor_boolean",
       "valor_varchar",
       "valor_decimal",
       "html_render",
       "valor",
       "form_control_name",
       "valor_multiple",
       "orden",
       "valor_lista",
       'esimagen',
       'galerias_id',
       'archivos_id',
    ];
}
