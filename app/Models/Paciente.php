<?php

namespace App\Models;

use App\Models\FormConsumoDrogas\FormConsumoDrogas;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    protected $table = 'hclinico.paciente';
    protected $primaryKey = 'pac_id';

    protected $fillable = [
        'pac_estado',
        'pac_primero_nombre',
        'pac_segundo_nombre',
        'pac_primer_apellido',
        'pac_segundo_apellido',
        'pac_identificacion',
        'pac_fecha_nacimiento',
        'pac_grupo_sanguineo',
        'pac_lateralidad',
        'pac_sexo',
        'pac_telefono',
        'pac_correo',
        'pac_imagen',
        'ciudad_id',
        'pac_direccion',
    ];

    //relacion uno a uno
    public function formOcupacional()
    {
        return $this->hasOne('App\Models\FormOcupacional','pac_id');
    }

    public function formConsuDro()
    {
        return $this->belongsTo(FormConsumoDrogas::class, "pac_id", "pac_id");
    }
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}




// $conTabac
// $conAlcho
// $conOtrDro1
// $conOtrDro2









