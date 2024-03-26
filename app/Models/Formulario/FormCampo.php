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
    protected $fillable = [
        'nombre',
        'titulo',
        'descripcion',
        'requerido',
        'marcado',
        'form_id',
        'tipo_campo_id',
        'form_control_name',
        'fcl_id',
        'orden',
        'form_secc_id',
        'par_id',
        'modificar',
        'deleted_at',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    public function valor()
    {
        return $this->belongsToMany(FormValor::class, 'crm.form_campo_valor', 'campo_id', 'valor_id');
    }
    public function tipo()
    {
        return $this->belongsTo(FormTipoCampo::class,'tipo_campo_id');
    }
    public function likert()
    {
        return $this->belongsToMany(FormCampoLikert::class, 'crm.form_campolikert_union', 'campo_id', 'fcl_id');
    }
    public function campoLikerts()
    {
        return $this->hasMany(CampoLikert::class, 'campo_id', 'id');
    }
    public function parametro()
    {
        return $this->belongsTo(Parametro::class, 'par_id', 'id');
    }

    public function formSeccion()
    {
        return $this->belongsTo(FormSeccion::class, 'form_secc_id', 'id');
    }
}
