<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormCampo extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.form_campo';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'nombre',
        'titulo',
        'descripcion',
        'requerido',
        'marcado',
        'form_id',
        'tipo_campo_id'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    public function valor()
    {
        return $this->belongsToMany(FormValor::class, 'crm.form_campo_valor', 'campo_id', 'valor_id');
    }
    public function tipo()
    {
        return $this->belongsTo(FormTipoCampo::class,'tipo_campo_id');
    }
}
