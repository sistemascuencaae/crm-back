<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParametroHijo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm.parametro_hijo';
    protected $fillable = ['parp_id', 'nombre', 'descripcion'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $primaryKey = 'id';

    // public function parametroPadre()
    // {
    //     return $this->belongsTo(Parametro::class, 'parp_id', 'id');
    // }
}
