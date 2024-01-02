<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormValor extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.form_valor';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'user_id',
        'pac_id',
        'valor_texto',
        'valor_date',
        'valor_array',
        'valor_json',
        'valor_boolean',
        'valor_decimal',
        'valor_entero',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
