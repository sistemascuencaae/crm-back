<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $table = 'public.bodega';
    use HasFactory;
    protected $primaryKey = 'bod_id';

    protected $fillable = [
        "bod_id",
        "bod_nombre",
        "dir_id",
        "bod_volumenneto",
        "bod_volumenbruto",
        "bod_activo",
        "locked",
        "bod_principal",
        "bod_promocion",
        "bod_promocion_default",
        "bod_codigo",
        "bod_enviar_saldo",
        "gbo_id",
        "ubi_id",
        "alm_id",
    ];
}
