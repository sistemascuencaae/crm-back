<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parametro extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm.parametro';
    protected $fillable = ['nombre', 'descripcion'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $primaryKey = 'id';

    public function parametroHijos()
    {
        return $this->hasMany(ParametroHijo::class, 'parp_id');
    }
}
