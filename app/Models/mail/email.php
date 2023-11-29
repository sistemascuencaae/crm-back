<?php

namespace App\Models\mail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $table = 'crm.email';

    protected $fillable = [
        'asunto',
        'cuerpo',
        'fase_id',
        'auto',
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
