<?php

namespace App\Models\api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ApiColeccion extends Model
{
    protected $table = 'crm.api_colecciones';

    protected $fillable = [
        'users_id',
        'nombre',
        'descripcion',
        'color',
        'orden',
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

    public function requests()
    {
        return $this->hasMany(ApiRequest::class, 'api_colecciones_id')->orderBy('orden', 'asc');
    }
}
