<?php

namespace App\Models\reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteLinkUsuario extends Model
{
    use HasFactory;

    protected $table = 'crm.reporte_link_usuario';
    protected $fillable = [
        "reporte_link_id",
        "user_id",
    ];

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

    public function usuario()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }

    public function reporteLink()
    {
        return $this->belongsTo(ReporteLink::class, "reporte_link_id", "id");
    }

}
