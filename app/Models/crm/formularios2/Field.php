<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    use HasFactory;

    protected $table = 'crm.field';
    protected $fillable = [
        "form_id",
        "label",
        "type",
        "required",
        "description",
        "order",
        "isactive",
        "opcion",
        "form_control_name",
        "margin",
        "url",
        "seccion_id",
        "group"
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

    public function dform()
    {
        // return $this->belongsTo(DForm::class, "id", "field_id");
        return $this->hasMany(DForm::class, "field_id", "id");
    }

}
