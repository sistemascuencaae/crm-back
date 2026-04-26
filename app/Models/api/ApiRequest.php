<?php

namespace App\Models\api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ApiRequest extends Model
{
    protected $table = 'crm.api_requests';

    protected $fillable = [
        'api_colecciones_id',
        'nombre',
        'metodo',
        'url',
        'headers',
        'params',
        'body_tipo',
        'body',
        'auth_tipo',
        'auth_data',
        'orden',
    ];

    protected $casts = [
        'headers'   => 'array',
        'params'    => 'array',
        'auth_data' => 'array',
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

    public function coleccion()
    {
        return $this->belongsTo(ApiColeccion::class, 'api_colecciones_id');
    }
}
