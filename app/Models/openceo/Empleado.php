<?php

namespace App\Models\openceo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'public.empleado';
    protected $primaryKey = 'emp_id';
    public $timestamps = false;
    protected $fillable = [
        "ent_id",
        "emp_abreviacion",
        "locked",
        "emp_tipo",
        "pve_id",
        "age_tipo",
        "emp_dsc_autorizado",
        "nem_id",
        "emp_activo",
        "usu_id",
        "emp_inicio",
        "emp_contrasena",
        "emp_num_gestiones",
        "emp_token",
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usu_id', 'usu_id');
    }
}
