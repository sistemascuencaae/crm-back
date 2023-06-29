<?php

namespace App\Models\crm;

use App\Models\User;
use App\Models\crm\Entidad;
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
        "fas_id",
        "nombre",
        "descripcion",
        "estado",
        "orden",
        "ent_id",
        "user_id",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "id");
    }

    public function entidad()
    {
        return $this->belongsTo(Entidad::class, "ent_id");
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
