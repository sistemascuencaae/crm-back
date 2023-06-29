<?php

namespace App\Models\crm;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableroUsuario extends Model
{
    
    protected $table = 'crm.tablero_user';
    protected $primaryKey = 'tu_id';
    protected $fillable = [
        "user_id",
        "tab_id",
    ];
    public function usuario()
    {
        return $this->hasMany(User::class,"id","user_id");
    }
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
