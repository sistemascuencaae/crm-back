<?php

namespace App\Models\crm\renegociaciones;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Pagare extends Model
{
    protected $table = 'crm.historial_pagare';

    protected $fillable = [
        'empresa',
        'cedula',
        'numeropago',
        'direccion',
        'fechafac',
        'f_ven',
        'nombre',
        'interes',
        'capital',
        'cuota',
        'valorl',
        'total',
        'totall',
        'inte',
        'iden_esposa',
        'nom_esposa',
        'iden_garante',
        'nom_garante',
        'iden_espgarante',
        'nom_espgarante',
        'cti_sigla',
        'alm_codigo',
        'pve_numero',
        'cfa_numero',
        'pve_id',
        'ubi_nombre',
        'ent_email',
        'dias_vista',
        'ent_id',
        'cli_id',
        'cfa_id',
        'ddo_doctran',
        'codigo_historial',
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["updated_at"] = Carbon::now();
    }

}
