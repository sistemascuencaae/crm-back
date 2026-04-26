<?php

namespace App\Models\api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ApiHistorial extends Model
{
    protected $table = 'crm.api_historial';

    public $timestamps = false;

    protected $fillable = [
        'users_id',
        'api_requests_id',
        'nombre',
        'metodo',
        'url',
        'headers',
        'params',
        'body_tipo',
        'body',
        'auth_tipo',
        'auth_data',
        'respuesta_status',
        'respuesta_headers',
        'respuesta_body',
        'respuesta_tiempo_ms',
        'respuesta_tamano_kb',
    ];

    protected $casts = [
        'headers'           => 'array',
        'params'            => 'array',
        'auth_data'         => 'array',
        'respuesta_headers' => 'array',
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["created_at"] = Carbon::now();
    }
}
