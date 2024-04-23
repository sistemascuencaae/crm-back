<?php

namespace App\Models\crm;

// use App\Models\crm\TipoCaso;

use App\Models\Formulario\FormularioTipoCaso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class TipoCaso extends Model
{

    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.tipo_caso';

    protected $fillable = ["nombre", "estado", "tab_id", "ctt_id"];

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

    public function cTipoTarea()
    {
        return $this->belongsTo(CTipoTarea::class, 'ctt_id');
    }

    public function formTipoCaso()
    {
        return $this->belongsTo(FormularioTipoCaso::class, 'id', 'tc_id');
    }
}
