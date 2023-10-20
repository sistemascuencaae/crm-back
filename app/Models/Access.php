<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    protected $table = 'hclinico.access';

    protected $fillable = [
        'profile_id',
        'menu_id',
        'view',
        'create',
        'edit',
        'delete',
        'report',
        'other',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

}
