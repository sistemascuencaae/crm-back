<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formulario extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.formulario';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'dep_id',
        'tipo'
    ];
    protected $hidden = ['created_at', 'deleted_at'];
    public function campo()
    {
        return $this->hasMany(FormCampo::class,"form_id");
    }

}
