<?php

namespace App\Models\crm;

use App\Models\crm\Tablero;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Fase extends Model
{
    use HasFactory;

    protected $table = 'crm.fase';

    protected $fillable = [
        "tab_id",
        "nombre",
        "descripcion",
        "generar_caso",
        "estado",
        "orden",
        "color_id",
        "fase_tipo"
    ];

    public function Caso()
    {
        return $this->hasMany(Caso::class, "fas_id");
    }

    public function tablero()
    {
        return $this->belongsTo(Tablero::class, "tab_id", "id");
    }

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