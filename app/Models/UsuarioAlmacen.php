<?php

namespace App\Models;

use App\Models\openceo\Almacen;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioAlmacen extends Model
{
    use HasFactory;
    
    protected $table = 'crm.usuario_almacen';
    protected $fillable = [
        "alm_id",
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

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, "alm_id", "alm_id");
    }

}