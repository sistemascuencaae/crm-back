<?php

namespace App\Models\openceo;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Politica extends Model
{
    use HasFactory;

    protected $table = 'politica';

    protected $primaryKey = 'pol_id';

    // La tabla no tiene created_at / updated_at
    public $timestamps = false;

    protected $fillable = [
        "pol_nombre",
        "pol_valordesde",
        "pol_valorhasta",
        "pol_por_desc",
        "pol_por_financia",
        "pol_por_pagconta",
        "pol_por_propago",
        "pol_diasplazo",
        "pol_nropagos",
        "pol_lineacredito",
        "pol_activo",
        "pol_tipocli",
        "locked",
        "pol_pordefecto",
        "pol_diasgracia",
        "pol_cuotasgratis",
        "pol_agrupador",
        "por_porcentaje_comision",
    ];

    // Accessor para que me muestre siempre el Activo o Inactivo de la politica
    protected $appends = ['pol_activo_texto', 'pol_tipocli_texto', 'pol_pordefecto_texto'];

    public function getPolActivoTextoAttribute()
    {
        return $this->pol_activo ? 'Activo' : 'Inactivo';
    }

    public function getPolTipoCliTextoAttribute()
    {
        return $this->pol_tipocli == 1 ? 'Cliente' : 'Proveedor';
    }

    public function getPolPorDefectoTextoAttribute()
    {
        return $this->pol_pordefecto ? 'Si' : 'No';
    }
}
