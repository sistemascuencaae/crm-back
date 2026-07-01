<?php

namespace App\Models\sts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesMultinivel extends Model
{
    use HasFactory;

    protected $table = 'clientes_multinivel';

    protected $fillable = [
	"cli_id",
	"corredor",
	"dias_parametro",
	"activo",
	"fecha_desvinculacion",
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
