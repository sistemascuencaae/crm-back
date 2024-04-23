<?php

namespace App\Models\crm;

use App\Models\crm\Estados;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

// lineas para auditar automaticamente
// implements Auditable 
// use AuditableTrait;
class Tablero extends Model
{
    use HasFactory;

    protected $table = 'crm.tablero';

    protected $fillable = ["nombre", "descripcion", "estado", "dep_id", "gal_id"];

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

    public function fase()
    {
        return $this->hasMany(Fase::class, "tab_id");
    }

    public function estados()
    {
        return $this->hasMany(Estados::class, "tab_id");
    }

    public function fondoTablero()
    {
        return $this->belongsTo(Galeria::class, "gal_id");
    }

}