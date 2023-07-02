<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Tablero extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'crm.tablero';

    protected $fillable = ["nombre", "descripcion", "estado", "titab_id", "dep_id"];

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

    public function tableroUsuario()
    {
        return $this->hasMany(TableroUsuario::class, "tab_id");
    }
}