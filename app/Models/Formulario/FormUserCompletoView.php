<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormUserCompletoView extends Model
{
    use HasFactory;
    protected $table = 'crm.form_user_completo';
    protected $fillable = [
        'nombre_formulario',
        'nombre_campo',
        'descripcion',
        'titulo',
        'tipo_campo',
        'orden',
        'valor_texto',
        'valor_entero',
        'valor_decimal',
        'valor_boolean',
        'valor_json',
        'valor_array',
        'valor_date',
        'created_at_valor',
        'updated_at_valor',
        'form_id',
        'user_id',
    ];
}
