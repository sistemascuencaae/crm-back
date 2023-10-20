<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormAptitudMedica extends Model
{
    protected $table = 'hclinico.formulario_aptitudmedica';
    protected $primaryKey = 'fam_id';
    protected $fillable = [
        'pac_id',
        'doc_id',
        'b_fecha_emision',
        'b_ingreso',
        'b_periodico',
        'b_reintegro',
        'b_retiro',
        'c_aptitud_medica_lavoral',
        'c_observaciones',
        'd_evalu_retiro',
        'd_condi_presuntiva',
        'd_condi_definitiva',
        'd_condi_no_aplica',
        'd_condi_reltra_si',
        'd_condi_reltra_noaplica',
        'e_recomendaciones_desc'
    ];


    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
