<?php

namespace App\Models\correo;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correo extends Model
{
    use HasFactory;

    protected $table = 'crm.correo';
    protected $fillable = [
        "nombre",
        "email_cliente",
        "auto_con_copia_para",
        "emails",
        "asunto",
        "cuerpo",
        "firma",
        "cuerpo2",
        "cuerpo3",
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

}
