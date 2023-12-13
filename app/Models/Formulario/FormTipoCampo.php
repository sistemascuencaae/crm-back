<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTipoCampo extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.form_tipo_campo';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'nombre',
        'descripcion',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

}
