<?php

namespace App\Models\crm;

use App\Models\crm\DActividadCliente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CActividadCliente extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.cactividad_cliente';

    protected $fillable = ["nombre", "tab_id"];

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

    public function dActividadCliente()
    {
        return $this->hasMany(DActividadCliente::class, "cac_id");
    }
}