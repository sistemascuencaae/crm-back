<?php

namespace App\Models;

use App\Models\Access;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'hclinico.profiles';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'name',
        'isactive',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function access()
    {
        return $this->hasMany(Access::class);
    }

}
