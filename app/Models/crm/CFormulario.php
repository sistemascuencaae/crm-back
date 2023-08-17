<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CFormulario extends Model
{

    protected $table = 'crm.cformulario';
    protected $fillable = [
        "id",
        "fas_id",
        "descripcion",
        "estado",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    public function dformulario()
    {
        return $this->hasMany(DFormulario::class, 'cform_id', 'id');
    }

}
