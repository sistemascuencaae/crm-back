<?php

namespace App\Models\configuracion;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CHorario extends Model
{
    use HasFactory;

    protected $table = 'crm.chorario';
    protected $fillable = ["nombre", "estado"];

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

    public function dhorario(){
        return $this->hasMany(DHorario::class, 'chorario_id', 'id');
    }

}
