<?php

namespace App\Models\Chat;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatFile extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "file_names",
        "resolution",
        "type",
        "size",
        "file",
        "uniqd"
    ];

    public function setCreatedAtAtribute($value)
    {
        date_default_timezone_set("America/Lima");
        $this->attributes["created_at"] = Carbon::now();
    }

    public function setUpdateAtAtribute($value)
    {
        date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"] = Carbon::now();
    }

    public function getSizeAttribute($size)
    {
        $size = (int) $size;
        $base = log($size) / log(1024);
        $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');
        return round(pow(1024, $base - floor($base)), 2) . $suffixes[floor($base)];
    }

    public function getNameFileAttribute()
    {
       $name = str_replace(" ", "-", $this->file_names);
       $newname = str_replace("_", "-", $name);// TAREAS PROGRAMAS -> TAREAS-PROGRAMAS
       return $newname;
    }

}
