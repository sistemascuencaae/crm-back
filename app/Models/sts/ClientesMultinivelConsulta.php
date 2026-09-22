<?php

namespace App\Models\sts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Log de consultas de identificación hechas por los corredores desde el formulario STS.
// Una fila por cada clic en la lupa, exista o no el cliente. Sin FK a public.cliente.
class ClientesMultinivelConsulta extends Model
{
    use HasFactory;

    protected $table = 'clientes_multinivel_consultas';

    protected $fillable = [
        "corredor",
        "identificacion",
        "cli_id",
        "existia",
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
