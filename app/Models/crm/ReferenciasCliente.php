<?php

namespace App\Models\crm;

use App\Models\crm\TelefonosReferencias;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenciasCliente extends Model
{
    use HasFactory;

    protected $table = 'crm.referencias_cliente';

    protected $fillable = [
        "cli_id",
        "ent_id",
        "cedula",
        "nombre1",
        "nombre2",
        "apellido1",
        "apellido2",
        "nombre_comercial",
        "fecha_nacimiento",
        "parentesco",
        "email",
        "direccion",
        "estado",
        "observacion",
        "valido",
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

    public function telefonos()
    {
        return $this->hasMany(TelefonosReferencias::class, "ref_id", "id");
    }

}