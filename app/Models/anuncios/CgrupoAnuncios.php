<?php

namespace App\Models\anuncios;

use Illuminate\Database\Eloquent\Model;

// 19-nov: cabecera de Grupo Anuncios. El CRUD lee y escribe con las funciones crm.fn_cgrupo_anuncios_*.
class CgrupoAnuncios extends Model
{
    protected $table = 'crm.cgrupo_anuncios';

    protected $fillable = [
        "nombre",
        "activo",
        "created_by",
        "updated_by",
    ];

    public function agencias()
    {
        return $this->hasMany(DgrupoAnuncios::class, "cgrupo_anuncios_id", "id");
    }
}
