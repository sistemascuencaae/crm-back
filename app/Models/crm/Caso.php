<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Caso extends Model
{
    // use AuditableTrait;

    use HasFactory;

    protected $table = 'crm.caso';

    protected $fillable = [
        "id",
        "fas_id",
        "nombre",
        "descripcion",
        "estado",
        "orden",
    ];

    public function Caso()
    {
        return $this->hasMany(Caso::class, "fas_id");
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
