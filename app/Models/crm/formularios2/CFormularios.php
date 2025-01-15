<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CFormularios extends Model
{
    use HasFactory;

    protected $table = 'crm.cformularios';
    protected $fillable = [
        "form_id"
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

    public function dformularios()
    {
        return $this->hasMany(DFormularios::class, "cform_id");
    }
}
