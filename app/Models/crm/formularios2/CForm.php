<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CForm extends Model
{
    use HasFactory;

    protected $table = 'crm.cform';
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

    public function dform()
    {
        return $this->hasMany(DForm::class, "cform_id");
    }

    public function form()
    {
        return $this->belongsTo(Form::class, "form_id", "id");
    }

    public function form2()
    {
        return $this->belongsTo(Form::class, "form_id");
    }
}
