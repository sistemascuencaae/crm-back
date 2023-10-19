<?php

namespace App\Models\crm\menu;

use App\Models\crm\menu\Submenu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'crm.menu';
    protected $fillable = ["icon", "title", "url", "caret"];

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

    public function submenu()
    {
        return $this->hasMany(Submenu::class, 'menu_id', 'id');
    }

}