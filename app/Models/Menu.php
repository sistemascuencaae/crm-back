<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'crm.menu';

    // code ya no se escribe (06-nov): la columna queda con lo que tenía y las filas nuevas nacen sin código.
    protected $fillable = [
        "module",
        "description",
        "name",
        "url",
        "icon",
        "padre_id",
        "titulo",
        "orden",
        "en_menu",
    ];

    protected $casts = [
        "padre_id" => "integer",
        "orden" => "integer",
        "en_menu" => "boolean",
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
