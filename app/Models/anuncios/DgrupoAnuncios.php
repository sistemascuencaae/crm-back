<?php

namespace App\Models\anuncios;

use Illuminate\Database\Eloquent\Model;

// 19-nov: agencias de cada grupo de Grupo Anuncios.
class DgrupoAnuncios extends Model
{
    protected $table = 'crm.dgrupo_anuncios';

    protected $fillable = [
        "cgrupo_anuncios_id",
        "agencia_id",
        "created_by",
        "updated_by",
    ];

    public function grupo()
    {
        return $this->belongsTo(CgrupoAnuncios::class, "cgrupo_anuncios_id", "id");
    }
}
