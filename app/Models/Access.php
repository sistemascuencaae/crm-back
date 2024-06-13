<?php

namespace App\Models;

use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    use HasFactory;
    protected $table = 'crm.access';

    protected $fillable = [
        'profile_id',
        'menu_id',
        'view',
        'create',
        'edit',
        'delete',
        'report',
        // 'other',
        'ejecutar'
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

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

}
