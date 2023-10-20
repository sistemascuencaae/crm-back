<?php

namespace App\Models\crm\menu;

use App\Models\crm\menu\Menu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuUsuario extends Model
{
    use HasFactory;

    protected $table = 'crm.menu_usuario';
    protected $fillable = ["user_id", "menu_id"];

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

    public function menu()
    {
        return $this->hasMany(Menu::class, "id", "menu_id");
    }

}