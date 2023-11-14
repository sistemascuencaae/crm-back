<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuntoVenta extends Model
{
    protected $table = 'public.puntoventa';
    use HasFactory;
    protected $primaryKey = 'pve_id';

    protected $fillable = [
        "pve_id",
        "alm_id",
        "pve_numero",
        "pve_nombre",
        "cco_id",
        "pve_responsable",
        "pve_activo",
        "locked",
        "bod_id",
        "pve_felectronica",
        "cco_id_cierre",
        "lpr_id",
        "reg_id",
    ];

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, "alm_id", "alm_id");
    }
}
