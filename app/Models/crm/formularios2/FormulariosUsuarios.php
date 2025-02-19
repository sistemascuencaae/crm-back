<?php

namespace App\Models\crm\formularios2;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormulariosUsuarios extends Model
{
    use HasFactory;

    protected $table = 'crm.formularios_usuarios';
    protected $fillable = [
        "form_id",
        "usu_id",
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

    public function usuario()
    {
        return $this->belongsTo(User::class, "usu_id", "id");
    }

    // public function formulario()
    // {
    //     return $this->belongsTo(Formularios::class, "form_id", "id");
    // }

}
