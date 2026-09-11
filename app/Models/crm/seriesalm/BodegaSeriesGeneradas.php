<?php

namespace App\Models\crm\seriesalm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BodegaSeriesGeneradas extends Model
{
    use HasFactory;

    protected $table = 'crm.aav_bodega_series';

    protected $fillable = [
        "codigo",
        "producto",
        "bod_id",
        "bodega",
        "serie",
        "serie_generada",
    ];
}
