<?php

namespace App\Models\reportes;

use App\Models\crm\Departamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteLink extends Model
{
    use HasFactory;

    protected $table = 'crm.reporte_link';
    protected $fillable = ["nombre", "link", "estado", "departamento_id", "descripcion"];

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

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, "departamento_id", "id");
    }

}
