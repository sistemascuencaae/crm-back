<?php

namespace App\Models\crm\formularios2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasFactory;

    protected $table = 'crm.form';
    protected $fillable = [
        "name",
        "description",
        "header",
        "footer",
        "color",
        "image",
        "image_company",
        "isactive",
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
    
    public function seccion()
    {
        return $this->hasMany(Seccion::class, "form_id", "id");
    }

    public function formUser()
    {
        return $this->hasMany(FormUser::class, "form_id", "id");
    }

    public function field(){
        return $this->hasMany(Field::class, 'form_id');
    }
}
