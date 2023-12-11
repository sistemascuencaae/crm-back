<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormCampoLikert extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'form_campo';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'nombre',
    ];
}
