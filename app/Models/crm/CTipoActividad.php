<?php

namespace App\Models\crm;

use App\Models\crm\DTipoActividad;
use App\Models\crm\Tablero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class CTipoActividad extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.ctipo_actividad';

    protected $fillable = ["nombre", "estado", "tab_id"];

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

    public function setDeletedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["deleted_at"] = Carbon::now();
    }

    public function dTipoActividad()
    {
        return $this->hasMany(DTipoActividad::class, 'id', 'cta_id');
    }

    public function tablero()
    {
        return $this->belongsTo(Tablero::class, 'tab_id', 'id');
    }
}