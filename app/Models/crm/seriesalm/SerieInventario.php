<?php

namespace App\Models\crm\seriesalm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SerieInventario extends Model
{
    use HasFactory;

    protected $table = 'crm.stock_pro_serie';
    protected $fillable = ["bod_id", "pro_id", "serie"];

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
