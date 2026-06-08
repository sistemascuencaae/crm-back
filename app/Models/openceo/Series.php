<?php

namespace App\Models\openceo;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    use HasFactory;

    protected $table = 'series';

    protected $fillable = [
        "codigo_lote",
        "user_id",
        "tpr_id",
        "cfa_id",
        "pro_id",
        "despacho_id",
        "observacion",
        "campo1",
        "campo2",
        "campo3",
        "campo4",
        "campo5",
        "campo6",
        "campo7",
        "campo8",
        "campo9",
        "campo10",
        "campo11",
        "campo12",
        "campo13",
        "campo14",
        "campo15",
        "campo16",
        "campo17",
        "campo18",
        "campo19",
        "campo20",
        "campo21",
        "campo22",
        "campo23",
        "campo24",
        "campo25",
        "campo26",
        "campo27",
        "campo28",
        "campo29",
        "campo30",
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
}
