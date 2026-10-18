<?php

namespace App\Models\api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ApiVariable extends Model
{
    protected $table = 'crm.api_variables';

    protected $fillable = [
        'users_id',
        'nombre',
        'valor',
        'descripcion',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
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
