<?php

namespace App\Models\crm\seriesalm;

use App\Models\openceo\Bodega;
use App\Models\openceo\Producto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SerieInventario extends Model
{
    use HasFactory;

    protected $table = 'crm.stock_pro_serie';
    protected $fillable = ["bod_id", "pro_id", "serie", "disabled"];

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

    public function producto()
    {
        return $this->belongsTo(Producto::class, "pro_id", "pro_id");
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, "bod_id", "bod_id");
    }

}
