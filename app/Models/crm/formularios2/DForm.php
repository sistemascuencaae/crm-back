<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DForm extends Model
{
    use HasFactory;

    protected $table = 'crm.dform';
    protected $fillable = [
        "cform_id",
        "field_id",
        "value",
        "seccion_id",
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
    
    public function field(){
    return $this->belongsTo(Field::class, 'field_id');
}

}
