<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormSeccion extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.formulario_seccion';
    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'form_id',
        'orden'
    ];
    protected $hidden = ['created_at', 'updated_at'];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class, 'form_id', 'id');
    }
}
