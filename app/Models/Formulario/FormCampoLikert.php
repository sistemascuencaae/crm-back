<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormCampoLikert extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.form_campo_likert';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'nombre',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    public function campoLikerts()
    {
        return $this->hasMany(CampoLikert::class, 'fcl_id', 'id');
    }
}
