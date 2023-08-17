<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Galeria extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.galerias';

    protected $fillable = ["titulo", "descripcion", "imagen", "caso_id", "tipo_gal_id", "sc_id"];

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

    public function tipo_galeria()
    {
        return $this->belongsTo(TipoGaleria::class, "tipo_gal_id");
    }
}
