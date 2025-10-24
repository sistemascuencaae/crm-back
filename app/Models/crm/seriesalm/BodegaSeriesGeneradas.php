<?php

namespace App\Models\crm\seriesalm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BodegaSeriesGeneradas extends Model
{
    use HasFactory;

    protected $table = 'crm.aav_bodega_series';

    protected $fillable = [
        "pro_codigo",
        "pro_nombre",
        "bod_id",
        "bodega",
        "item_numero",
        "serie_id",
        "serie",
        "serie_generada",
    ];
}
