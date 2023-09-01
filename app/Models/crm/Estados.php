<?php

namespace App\Models\crm;

use App\Models\crm\TipoEstado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estados extends Model
{
    use HasFactory;

    protected $table = 'crm.estados';

    protected $fillable = ["nombre", "estado", "tab_id", "tipo_estado_id"];

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

    public function tipo_estado()
    {
        return $this->belongsTo(TipoEstado::class, "tipo_estado_id");
    }
}