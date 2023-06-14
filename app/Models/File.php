<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;
    protected $table = 'public.entidad';

    protected $fillable = [
        "ent_id",
        "ent_identificacion",
        "ent_nombres",
        "ent_apellidos",
        "ent_contacto",
        "tit_id",
        "ent_fechanacimiento",
        "ent_direccion_principal",
        "locked",
        "ent_foto",
        "ent_tipo_identificacion",
        "alm_id",
        "ent_email",
        "ent_telefono_principal",
        "tii_id",
    ];

    public function Cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
