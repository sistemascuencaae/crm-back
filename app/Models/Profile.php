<?php

namespace App\Models;

use App\Models\Access;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'crm.profiles';
    // protected $primaryKey = 'id';
    
    protected $fillable = [
        'name',
        'isactive',
    ];

    // protected $hidden = [
    //     'created_at',
    //     'updated_at',
    // ];

    // protected $casts = [
    //     'id' => 'integer',
    // ];

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

    public function access()
    {
        return $this->hasMany(Access::class, 'profile_id', 'id');
    }

}
