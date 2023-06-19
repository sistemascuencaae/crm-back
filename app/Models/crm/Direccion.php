<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Direccion extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'public.direccion';

    protected $primaryKey = 'dir_id';

    public $timestamps = false;

    protected $fillable = [
        "dir_id",
        "dir_calle_principal",
        "dir_numeracion",
        "dir_calle_secundaria",
        "dir_nombre_edificio",
        "dir_piso",
        "dir_oficina",
        "dir_indicaciones_adicionales",
        "dir_latitud",
        "dir_longitud",
        "locked",
        "dir_imagen",
    ];
}