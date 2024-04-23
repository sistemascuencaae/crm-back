<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormularioTipoCaso extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.formulario_tipo_caso';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'id',
        'form_id',
        'tc_id',
        'tab_id'
    ];
    protected $hidden = ['created_at', 'deleted_at'];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class,  'form_id');
    }

}
